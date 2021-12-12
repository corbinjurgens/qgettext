<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Gettext\Translator;
use Gettext\GettextTranslator;
use Gettext\Loader\MoLoader;
use Gettext\Generator\ArrayGenerator;
use Gettext\Scanner\PhpScanner;
use Gettext\Scanner\JsScanner;
use Corbinjurgens\QGetText\Concerns\CustomTranslations as Translations;
use Gettext\Translation;
use Gettext\Merge;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Events\LocaleUpdated;
use Symfony\Component\Finder\Finder;


class QGetTextContainer
{

	use Concerns\LoadersAndGenerators;
	use Concerns\Paths;

	const NATIVE_MODE = 1;
	const EMULATED_MODE = 2;

	/**
	 * Current locale set from applicaation
	 */
	static $locale;

	static $force_locale;

	static $booted = false;

	/** Last used native locale, useed to check if current gettext locale needs to be updated via setlocale() */
	static $locale_native;

	public static function boot($locale = null){
		if (static::$booted === true){
			return;
		}
		// Listen to any later locale updates
		Event::listen(function(LocaleUpdated $event){
			static::setLocale($event->locale);
		});
		static::setLocale($locale ?? app()->getLocale());
		static::$booted = true;

		// Check config
		$max_emulated = config('qgettext.max_emulated', null);
		if (isset($max_emulated)){
			$max_emulated = (int) $max_emulated;
			if ($max_emulated === 0){
				throw new \Exception("Max emulated must be null, or a number 1 or over");
			}
		}
	}

	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup($locale = null){
		static::boot($locale);
		return static::getMode() === static::EMULATED_MODE ? static::startupEmulated($locale) : static::startupNative($locale);

	}
	
	public static function getMode(){
		return config('qgettext.mode', static::NATIVE_MODE);
	}

	/**
	 * Set to a custom closure if you want to modify locale received from app()->getLocale()
	 */
	public static $localeGetter;

	/**
	 * Update locale
	 */
	public static function setLocale($locale){
		if (isset(static::$localeGetter)){
			return static::$locale = (static::$localeGetter)($locale);
		}
		return static::$locale = $locale;
	}

	public static function getLocale(){
		return static::$force_locale ?? static::$locale;
	}

	public static function getDefaultDomain(){
		return config('qgettext.default_domain', 'messages');
	}

	static $translator;

	static $translators_emulated = [];
	/**
	 * Use native gettext php functions
	 * Requires gettext installed, and is restrictive as you must also install
	 * the locale to the server, and ensure it matches exactly
	 */
	public static function startupNative($locale = null){
		if (isset(static::$translator)){
			return static::$translator;
		}
		$locale = $locale ?? static::getLocale();
		$translator = new GettextTranslator($locale);
		
		$default = static::getDefaultDomain();
		$path = config('qgettext.path');
		foreach(config('qgettext.domains') as $domain){
			$translator->loadDomain($domain, $path, $domain == $default);
		}
		return static::$translator = $translator;
	}

	static $emulatedDomainArgs = [
		'dngettext' => 0,
		'dgettext' => 0,
		'dpgettext' => 0,
		'dnpgettext' => 0
	];
	
	/** Starting up too many languages may cause memory issues. Use config qgettext.max_emulated to configure a max */
	static $started_emulated_list = [];

	/**
	 * Prepare the translations instance freely without the php drama
	 */
	public static function startupEmulated($locale = null){
		$locale = $locale ?? static::getLocale();
		if (isset(static::$translators_emulated[$locale])){
			return static::$translators_emulated[$locale];
		}
		
		static::startupEmulatedShift($locale);
		$translator = new Translator();
		$translator->defaultDomain(static::getDefaultDomain());
		return static::$translators_emulated[$locale] = $translator;
	}

	protected static function startupEmulatedShift($locale){
		static::$started_emulated_list[] = $locale;
		$limit = config('qgettext.max_emulated', null);
		if (!isset($limit)){
			return;
		}
		if ($limit >= count(static::$started_emulated_list)){
			return;
		}
		$shited = array_shift(static::$started_emulated_list);
		unset(static::$translators_emulated[$shited]);
	}

	public static function load($translator, $name, $arguments){
		static::getMode() === static::EMULATED_MODE ? static::loadEmulated($translator, $name, $arguments) : static::loadNative($translator, $name, $arguments);
	}

	public static function loadNative($translator, $name, $arguments){
		// If language is not yet used or changed, update it
		if (static::$locale_native !== static::getLocale()){
			$translator->setLanguage(static::getLocale());
			static::$locale_native = static::getLocale();
		}
	}

	static $loaded_emulated = [];

	/**
	 * Only loads domains as needed
	 */
	public static function loadEmulated($translator, $name, $arguments){
		$locale = static::getLocale();
		$domain = isset(static::$emulatedDomainArgs[$name]) ? $arguments[static::$emulatedDomainArgs[$name]] : static::getDefaultDomain();
		
		if (isset(static::$loaded_emulated[$locale][$domain])){
			return static::$loaded_emulated[$locale][$domain];
		}
		$file = config('qgettext.path') . DIRECTORY_SEPARATOR . $locale . "/LC_MESSAGES/" . $domain . ".mo";
		if (!\File::exists($file)){
			return static::$loaded_emulated[$locale][$domain] = false;
		}
		$loader = new MoLoader();

		$loading = $loader->loadFile($file);
		$loading->setDomain($domain);
		$arrayGenerator = new ArrayGenerator();
		$translator->addTranslations($arrayGenerator->generateArray($loading));
		return static::$loaded_emulated[$locale][$domain] = true;

	}
	
	public function __call($name, $arguments){
		$translator = static::startup();
		static::load($translator, $name, $arguments);
		return $translator->$name(...$arguments);
	}

	/**
	 * Scan this sites files and upload the results to the shared location
	 * ready to be edited via the UI either on this site or elsewhere that has
	 * access to the shared location
	 */
	public static function scan($command = null){

		static::syncBase();

		$translations = [];
		foreach(config('qgettext.domains') as $domain){
			$translations[] = Translations::create($domain);
		}
		$phpScanner = new PhpScanner(...$translations);
        $phpScanner->setDefaultDomain(static::getDefaultDomain());
		$phpScanner->setFunctions(config('qgettext.scan.php_mapping') ?? config('qgettext.scan.mapping'));

		$jsScanner = new JsScanner(...$translations);
        $jsScanner->setDefaultDomain(static::getDefaultDomain());
		$jsScanner->setFunctions(config('qgettext.scan.js_mapping') ?? config('qgettext.scan.mapping'));

		$finder = new Finder();
        $finder->files()->in(config('qgettext.scan.in', base_path()))->name(config('qgettext.scan.pattern', '*.php'));

        foreach($finder as $file){
			$filepath = $file->getRealPath();
			// custom processing, can include a single file being scanned in multiple ways and will then continue to next file
			foreach(config('qgettext.scan.custom', []) as $key => $value){
				if (\Str::endsWith($filepath, $key)){
					$custom_class = new $value();
					$custom_class->loadScanners($phpScanner, $jsScanner);
					$custom_class->scanFile($filepath);
					continue 2;
				}
			}

			// php scanner
			foreach(config('qgettext.scan.php', []) as $value){
				if (\Str::endsWith($filepath, $value)){
					$phpScanner->scanFile($filepath);
					break;
				}
			}

			// js scanner
			foreach(config('qgettext.scan.js', []) as $value){
				if (\Str::endsWith($filepath, $value)){
					$jsScanner->scanFile($filepath);
					break;
				}
			}
			
        }

		$disk = static::pathDisk();
        foreach ($translations as $translation) {
			$domain = $translation->getDomain();
			$target = "base/$domain.po";
			if ($disk->exists($target)){
				$old = static::fromPo($disk->path($target));
				$changes = static::compare($translation, $old, $command);
				if ($changes === false){
					$command->error("Process cancelled. Try again.");
					return;
				}
				if ($changes){
					static::updateCompare($changes, $domain);
				}
				static::flagsComments($translation, $old, $changes);
			}else{
				static::flagsComments($translation);
			}
			static::toPo($translation, $disk->path($target));
        }

		static::uploadBase();

		return $disk->path("base");

	}

	public static function flagsComments($translation, $old = null, $changes = null){
		foreach($translation as $key => $value){
			$target_key = array_search($key, $changes ?? []);
			$changed = true;
			if ($target_key === false){
				$changed = false;
				$target_key = $key;
			}

			$old_value = isset($old) ? $old->getTranslations()[$target_key] ?? null : null;
			
			if (isset($old_value)){
				// set to old comments
				$value = $translation->addOrMergeId($key, $old_value, Merge::COMMENTS_THEIRS | Merge::FLAGS_OURS);
			}

			$comments = $value->getComments()->toArray();

			if (static::strpostFind($comments, "CREATED:") === false){
				$value->getComments()->add("CREATED:" . now()->format("Y-m-d H:i:s"));
			}

			if ($changed === true){
				$changed_max = static::strpostMax($comments, "CHANGED:");
				$changed_max++;
				$value->getComments()->add("CHANGED:{$changed_max}:" . $target_key);

				if (($updated = static::strpostFind($comments)) !== false){
					$value->getComments()->delete($updated);
				}
				$value->getComments()->add("UPDATED:" . now()->format("Y-m-d H:i:s"));
			}
		}
	}

	protected static function strpostFind($array, $search = "UPDATED:"){
		foreach($array as $item){
			if (strpos($item, $search) === 0){
				return $item;
			}
		}
		return false;
	}

	protected static function strpostCount($array, $search = "UPDATED:"){
		$count = 0;
		foreach($array as $item){
			if (strpos($item, $search) === 0){
				$count++;
			}
		}
		return $count;
	}

	protected static function strpostMax($array, $search = "UPDATED:"){
		$max = 0;
		foreach($array as $item){
			if (strpos($item, $search) === 0){
				$current = explode(":", $item, 3);
				if (isset($current[1]) && is_numeric($current[1])){
					$current_num = (int) $current[1];
					if ($current_num > $max){
						$max = $current_num;
					}
				}
			}
		}
		return $max;
	}

	/**
	 * Compare old and new translation state,
	 * if passing $command from a artisan command, it will use that to
	 * ask user which new translation equates to old
	 */
	public static function compare($translations_new, $translations_existing, $command = null){
		$trans_new = $translations_new->getTranslations();
		$trans_new_only = [];
		$trans_existing = $translations_existing->getTranslations();
		foreach($trans_new as $k => $v){
			if (!isset($trans_existing[$k])){
				$trans_new_only[] = $k;
			}
		}

		$trans_existing_only = [];
		foreach($trans_existing as $k => $v){
			$trans_existing_only[] = $k;
		}
		
		if (!isset($command)){
			return [$trans_existing_only, $trans_new_only];
		}

		// Command
		$results = [];
		$looper = $trans_new_only;
		if (!$trans_new_only){
			return [];
		}
		$total = count($looper);
		$current = 1;
		$command->info("Beginning merge process");
		$command->info("Be sure to carefully check if any of the 'new' texts are actually changed text");
		$command->comment("-------");
		while(($new = array_shift($looper)) !== null){
			$command->comment("$current of $total");
			$command->info("See the following new text:");
			$command->newLine();
			$command->line($new);
			$command->newLine();
			$command->info("Is this new text changed from a previous text:");
			$command->newLine();
			foreach($trans_existing_only as $key => $old){
				$command->info(($key + 1) . ":");
				$command->line($old);
			}

			$res = $command->ask("Or is it unrelated? Enter the number or press enter to skip this text...\nTo view current texts references, enter 'info', or 'info 1' to view a specific previous text. Enter 'skip' to skip all");
			while(!is_numeric($res)){
				if (!$res){
					$current++;
					continue 2;
				}
				if ($res === "skip"){
					return [];
				}
				if ($res === "info"){
					$command->info(join("\n", static::outputInfo($trans_new[$new])));
					$res = $command->ask("Enter the number or press enter to skip...");
					continue;
				}
				if (strpos($res, "info") === 0){
					$key = trim(substr($res, 4));
					if (!is_numeric($key)){
						$command->error("$key must be a number");
						$res = $command->ask("Try again:");
						continue;
					}
					if (!isset($trans_existing_only[$key - 1])){
						$command->error("$key is not a valid option");
						$res = $command->ask("Try again:");
						continue;
					}
					$command->info(join("\n", static::outputInfo($trans_existing[$trans_existing_only[$key - 1]])));
					$res = $command->ask("Enter the number or press enter to skip...");
					continue;
				}
				$res = $command->ask("Try again:");
			}

			if (!isset($trans_existing_only[$res - 1])){
				$command->error("This item was not found in the list");
				array_unshift($looper, $new);
				continue;
			}

			$old = $trans_existing_only[$res - 1];

			// Definitely its a changed key
			// Check that the references
			$ref_new = $trans_new[$new]->getReferences()->toArray();
			$ref_existing = $trans_existing[$old]->getReferences()->toArray();
			if ($ref_new !== $ref_existing){
				// References are different
				$command->info("The references between old and new text is not the same. There is a chance you have missed updating one of the texts");
				$command->newLine();

				$command->table(
					['Old text:', 'New text:'],
					[
						[
							$old,
							$new,
						],
						[
							join(",", static::outputInfo($trans_existing[$old])),
							join(",", static::outputInfo($trans_new[$new]))
						]
					]
				);
				$ask = $command->choice("Is everything OK? Press n to cancel process, or Y (default) to continue as is", ['Y', 'n'], 'Y');
				if ($ask === 'n'){
					return false;
				}
			}

			$results[$old] = $new;
			unset($trans_existing_only[$res - 1]);
			$current++;
		}
		$command->info("All done");
		return $results;
		

	}

	/**
	 * Take a changes array (old key => new key)
	 */
	public static function updateCompare($changes, $domain){
		$changesBefore = [];
		if (static::pathDisk()->exists("base/{$domain}_changes.json")){
			$changesBefore = json_decode(static::pathDisk()->get("base/{$domain}_changes.json"), true);
		}
		$changesBefore[] = $changes;
		static::pathDisk()->put("base/{$domain}_changes.json", json_encode($changesBefore));
	}

	protected static function outputInfo($translation){
		$res = [];

		$join = array_map(function($item){
			return join(",", $item);
		},$translation->getReferences()->toArray());
		foreach($join as $k => $v){
			$res[] = $k .":" . $v;
		}
		return $res;

	}

	/**
	 * Download current sites base
	 */
	public static function syncBase(){
		$path = static::pathDisk();
		$path->deleteDirectory('base');

		$shared = static::disk('shared');
		$shared_base = static::diskPath('shared', static::thisSite(), 'base');

		foreach($shared->allFiles($shared_base) as $file){
			$stream = $shared->readStream($file);
			$relativePath = str_replace($shared_base . DIRECTORY_SEPARATOR, '', $file);
			$path->put("base/$relativePath", $stream);
		}
	}

	/** 
	 * Upload current sites base 
	 */
	public static function uploadBase(){
		$disk = static::disk('shared');
		$upload_base = static::diskPath('shared', static::thisSite() . DIRECTORY_SEPARATOR . 'base');
		$uploaded = [];
		foreach((new Finder())->files()->in(static::basePath()) as $file){
			$stream = fopen($file->getRealPath(), "r");
			$key = $upload_base . DIRECTORY_SEPARATOR . $file->getRelativePathname();
			$uploaded[] = $key;
			$disk->put($key, $stream);
		}

		foreach($disk->allFiles($upload_base) as $file){
			if (!isset($uploaded[$file])){
				$disk->delete($upload_base . DIRECTORY_SEPARATOR . $file);
			}
		}
	}

	/**
	 * Look at files in current sites locale, and save po to mo
	 */
	public static function dump(){
		foreach((new Finder())->files()->in(static::basePath()) as $file){
			$domain = pathinfo($file->getRelativePathname(), PATHINFO_FILENAME);
			$baseTranslations = static::fromPo($file->getRealPath());

			foreach((new Finder())->directories()->in(static::path())->exclude('base')->depth(0) as $file){

				$locale = $file->getRelativePathname();
				$target = static::path($locale . DIRECTORY_SEPARATOR . "LC_MESSAGES" . DIRECTORY_SEPARATOR . $domain);

				if (\File::exists($target . ".po")){
					$targetTranslations = static::fromPo($target . ".po");
					$targetTranslations = $targetTranslations->mergewith($baseTranslations, Merge::HEADERS_THEIRS | Merge::FLAGS_THEIRS | Merge::TRANSLATIONS_OURS);
					//dd($targetTranslations);
				}else{
					$targetTranslations = $baseTranslations;
				}

				\File::ensureDirectoryExists(static::path($locale . DIRECTORY_SEPARATOR . "LC_MESSAGES"));
				static::toPo($targetTranslations, $target . ".po");

				static::toMo($targetTranslations, $target . ".mo");
			}
		}
		
	}




}
