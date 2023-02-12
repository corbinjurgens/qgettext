<?php

namespace Corbinjurgens\QGetText\Concerns;

use Corbinjurgens\QGetText\Concerns\CustomTranslations as Translations;
use Gettext\Translations as BaseTranslations;
use Gettext\Translation;

use Gettext\Scanner\PhpScanner;
use Gettext\Scanner\JsScanner;
use Gettext\Merge;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

trait ScanAndSync {

	/**
	 * @param BaseTranslations[] $translations;
	 */
	public static function keyByDomain($translations){
		$result = [];
		/** @var BaseTranslations */
		foreach($translations as $translation){
			$result[$translation->getDomain()] = $translation;
		}
		return $result;
	}

	public static function sync(Command $command = null){
		$translations_new = static::keyByDomain(static::scan());
		$translations_existing = static::keyByDomain(static::current());
		$domains = array_values(array_unique(array_merge(array_keys($translations_new), array_keys($translations_new))));
		if (isset($command) && !empty($translations_existing)){
			foreach($domains as $domain){
				if (!isset($translations_existing[$domain])) continue;

				list($new_only, $existing_only) = static::compare($translations_new[$domain], $translations_existing[$domain]);
				$map = static::merge($translations_new[$domain],$translations_existing[$domain], $new_only, $existing_only, $command);
				if ($map){
					static::shift($translations_new[$domain], $map);
					static::shiftCompare($map, $domain);
				}
			}
		}
		static::update($translations_new);
	}

	/**
	 * @param \Gettext\Translations[] $translations
	 */
	public static function update($translations){
		$disk = static::disk();
		foreach(config('qgettext.locales') as $locale){
			$LC_MESSAGES = $disk->folder("$locale/LC_MESSAGES");
			if (!$LC_MESSAGES->exists()) $LC_MESSAGES->makeDirectory();
			foreach($translations as $translation){
				$domain_file = $LC_MESSAGES->file($translation->getDomain() . '.po');
				
				if ($domain_file->exists()){
					$locale_translations = $translation->mergeWith(static::fromPo($domain_file->path()));
				}else{
					$locale_translations = (clone $translation)->setLanguage($locale);
				}

				static::toPo($locale_translations, $domain_file->path());
			}
		}
		$base_folder = $disk->folder('base');
		if (!$base_folder->exists()) $base_folder->makeDirectory();
		foreach($translations as $translation){
			$domain_file = $base_folder->file($translation->getDomain() . '.po');

			static::toPo($translation, $domain_file->path());
		}
	}

	public static function shift(BaseTranslations $translations, $map = []){
		$disk = static::disk();
		foreach(config('qgettext.locales') as $locale){
			$LC_MESSAGES = $disk->folder("$locale/LC_MESSAGES");
			if (!$LC_MESSAGES->exists()) $LC_MESSAGES->makeDirectory();
			$domain_file = $LC_MESSAGES->file($translations->getDomain() . '.po');

			if ($domain_file->exists()){
				$current_translations = static::fromPo($domain_file->path());
				$current_translations->remap($map);
				static::toPo($current_translations, $domain_file->path());
			}
		}
	}

	/**
	 * Scan code for translations
	 * 
	 * @return \Gettext\Translations[]
	 */
	public static function scan(){

		$translations = [];
		foreach(config('qgettext.domains') as $domain){
			$translations[] = Translations::create($domain, config('qgettext.source_locale'));
		}
		$phpScanner = new PhpScanner(...$translations);
        $phpScanner->setDefaultDomain(static::getDefaultDomain());
		$phpScanner->setFunctions(config('qgettext.scan.php_mapping', []) + config('qgettext.scan.mapping'));

		$jsScanner = new JsScanner(...$translations);
        $jsScanner->setDefaultDomain(static::getDefaultDomain());
		$jsScanner->setFunctions(config('qgettext.scan.js_mapping', []) + config('qgettext.scan.mapping'));

		// Files to look in
		$finder = new Finder();
        $finder->files()->in(config('qgettext.scan.in', base_path()))->name(config('qgettext.scan.pattern', '*.php'));
		
        foreach($finder as $file){
			$filepath = $file->getRealPath();
			// custom processing, can include a single file being scanned in multiple ways and will then continue to next file
			// used for blade files to first parse into raw php, then check
			foreach(config('qgettext.scan.custom', []) as $key => $value){
				if (\Str::endsWith($filepath, $key)){
					try{
						$custom_class = new $value();
						$custom_class->loadScanners($phpScanner, $jsScanner);
						$custom_class->scanFile($filepath);
					}catch(\Throwable $e){
						\Log::error($e);
					}
					continue 2;
				}
			}

			// php scanner
			foreach(config('qgettext.scan.php', []) as $value){
				if (\Str::endsWith($filepath, $value)){
					try{
						$phpScanner->scanFile($filepath);
					}catch(\Throwable $e){
						\Log::error($e);
					}
					
					break;
				}
			}

			// js scanner
			foreach(config('qgettext.scan.js', []) as $value){
				if (\Str::endsWith($filepath, $value)){
					try{
						$jsScanner->scanFile($filepath);
					}catch(\Throwable $e){
						\Log::error($e);
					}
					
					break;
				}
			}
			
        }

		return $translations;
	}

	public static function exampleSetHeaders(BaseTranslations $translations){
		$translations->setLanguage('de');
		$translations->getHeaders()->setPluralForm(2, "(n != 1)");
	}


	/**
	 * Compare old and new translation key state
	 * Finds what keys are only in new, and what are only in existing
	 * 
	 * @return array
	 */
	public static function compare(BaseTranslations $translations_new, BaseTranslations $translations_existing){
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
			if (!isset($trans_new[$k])){
				$trans_existing_only[] = $k;
			}
		}
		
		return [$trans_new_only, $trans_existing_only];
	}

	/**
	 * Returns an array of old translation to new tranlsation
	 * 
	 * @return array 
	 */
	public static function merge(BaseTranslations $translations_new, BaseTranslations $translations_existing, $trans_new_only, $trans_existing_only, Command $command){
		$trans_new = $translations_new->getTranslations();
		$trans_existing = $translations_existing->getTranslations();

		// Command
		$results = [];
		$looper = $trans_new_only;
		if (!$trans_new_only){
			return [];
		}
		$total = count($looper);
		$current = 1;
		$command->info("There are new texts in your base code. Beginning merge process");
		$command->info("Be sure to carefully check if any of the 'new' texts are actually changed text");
		$command->comment("-------");
		while(($new = array_shift($looper)) !== null){
			$command->comment("Text $current of $total");
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

			$res = $command->ask("Or is it unrelated? Enter the number of the old text that it equates to, or press enter to proceed as is...");
			while(!is_numeric($res)){
				if (!$res){
					$current++;
					continue 2;
				}
				if ($res === 'help'){
					$command->info("To view current texts references, enter 'info', or 'info 1' to view a specifc text. Enter 'skip' to skip all if you know there are no changed texts");
					continue;
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
	public static function shiftCompare($changes, $domain){
		static::shiftData(function($data) use ($changes, $domain){
			if (!isset($data['domains'][$domain]['changes'])) $data['domains'][$domain]['changes'] = [];
			$data['domains'][$domain]['changes'][] = $changes;
			return $data;
		});
	}

	public static function shiftData($closure, $disk = null){
		if (!static::disk()->folder('base')->exists()) static::disk()->folder('base')->makeDirectory();
		$file = $file ?? static::disk()->folder('base')->file("data.json");
		$contents = $closure($file->exists() ? json_decode($file->get(), true) : []);
		$file->put(json_encode($contents));
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
	 * @return \Gettext\Translations[]
	 */
	public static function current(){
		$translations = [];
		$disk = static::disk();
		if (!$disk->folder('base')->exists()) return $translations;

		foreach((new Finder())->files()->in($disk->folder('base')->path())->name('*.po') as $file){
			$domain = pathinfo($file->getRelativePathname(), PATHINFO_FILENAME);
			$translations[] = static::fromPo($file->getRealPath(), $domain);
		}

		return $translations;
	}

	/**
	 * Look at files in current sites locale, and save po to mo
	 */
	public static function dump(){
		$disk = static::disk();
		foreach(config('qgettext.locales') as $locale){
			$LC_MESSAGES = $disk->folder("$locale/LC_MESSAGES");
			if (!$LC_MESSAGES->exists()) continue;

			foreach((new Finder())->files()->in($LC_MESSAGES->path())->name('*.po') as $file){
				$domain = pathinfo($file->getRelativePathname(), PATHINFO_FILENAME);
				static::toMo(static::fromPo($file->getRealPath(), $domain), $LC_MESSAGES->file($domain . '.mo')->path());
			}
		}
	}

}