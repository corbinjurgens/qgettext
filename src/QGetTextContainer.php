<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Gettext\Translator;
use Gettext\GettextTranslator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\LoaderInterface;
use Gettext\Loader\PoLoader;
use Gettext\Loader\MoLoader;
use Gettext\Generator\GeneratorInterface;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\ArrayGenerator;
use Gettext\Scanner\PhpScanner;
use Gettext\Scanner\JsScanner;
use Gettext\Translations;
use Gettext\Merge;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Events\LocaleUpdated;
use Symfony\Component\Finder\Finder;


class QGetTextContainer
{
	static $emulated = false;

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
	}

	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup($locale = null){
		static::boot($locale);
		return static::$emulated ? static::startupEmulated($locale) : static::startupNative($locale);

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

	/**
	 * Prepare the translations instance freely without the php drama
	 */
	public static function startupEmulated($locale = null){
		$locale = $locale ?? static::getLocale();
		if (isset(static::$translators_emulated[$locale])){
			return static::$translators_emulated[$locale];
		}
		
		$translator = new Translator();
		$translator->defaultDomain(static::getDefaultDomain());
		return static::$translators_emulated[$locale] = $translator;
	}

	public static function load($translator, $name, $arguments){
		static::$emulated ? static::loadEmulated($translator, $name, $arguments) : static::loadNative($translator, $name, $arguments);
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
	public static function scan(){

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

		$path = static::basePath();
        foreach ($translations as $translation) {
			$domain = $translation->getDomain();
            \File::ensureDirectoryExists($path);
			static::toPo($translation, $path . DIRECTORY_SEPARATOR . $domain . ".po");
        }

		return $path;

	}

	public static function path($path = null){
		return config('qgettext.path') . (is_string($path) && $path !== "" ? (DIRECTORY_SEPARATOR . $path) : '');
	}
	
	public static function basePath(){
		return static::path('base');
	}

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

	public static function fromPo(string $filename){
		$loader = new PoLoader();
		return static::from($filename, $loader);
	}

	public static function fromMo(string $filename){
		$loader = new MoLoader();
		return static::from($filename, $loader);
	}

	public static function from(string $filename, LoaderInterface $loader){
		return $loader->loadFile($filename);
	}

	public static function toPo(Translations $translations, string $filename){
		$generator = new PoGenerator();
		return static::to($translations, $filename, $generator);
	}

	public static function toMo(Translations $translations, string $filename){
		$generator = new MoGenerator();
		return static::to($translations, $filename, $generator);
	}

	public static function to(Translations $translations, string $filename, GeneratorInterface $generator){
		return $generator->generateFile($translations, $filename);
	}




}
