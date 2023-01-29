<?php

namespace Corbinjurgens\QGetText\Concerns;

use Corbinjurgens\QGetText\Concerns\CustomTranslations as Translations;
use Gettext\Translation;

use Gettext\Scanner\PhpScanner;
use Gettext\Scanner\JsScanner;
use Gettext\Merge;
use Symfony\Component\Finder\Finder;

trait ScanAndSync {

	/**
	 * Scan this sites files and upload the results to the shared location
	 * ready to be edited via the UI either on this site or elsewhere that has
	 * access to the shared location
	 * 
	 * for now only possible to run from an artisan command due to merging changes
	 */
	public static function scan($command = null){

		static::syncBase();

		$translations = [];
		foreach(config('qgettext.domains') as $domain){
			$translations[] = Translations::create($domain);
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

		$disk = static::disk("local")->shiftBase('base');
		if (!$disk->exists()) $disk->makeDirectory();

        foreach ($translations as $translation) {
			static::exampleSetHeaders($translation);////TEST
			$domain = $translation->getDomain();
			$disk->setFile("$domain.po");
			static::toPo($translation, $disk->path());
        }

		static::scanComplete($translations);

		return $disk->clearFile()->path();

	}

	public static function exampleSetHeaders(Translations $translations){
		$translations->setLanguage('de');
		$translations->getHeaders()->setPluralForm(2, "(n != 1)");
	}

	/** 
	 * Upload current sites base 
	 */
	public static function scanComplete($translations){
		// 
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
		static::updateData(function($data) use ($changes, $domain){
			if (!isset($data['domains'][$domain]['changes'])) $data['domains'][$domain]['changes'] = [];
			$data['domains'][$domain]['changes'][] = $changes;
			return $data;
		});
	}

	public static function updateData($closure, $disk = null){
		$disk = $disk ?? static::disk('local')->shiftBase('base')->setFile("data.json");
		$contents = $closure($disk->exists() ? json_decode($disk->get(), true) : []);
		$disk->put(json_encode($contents));
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
		$path = static::disk('local');
		$path->deleteDirectory('base');

		$shared = static::disk('shared')->shiftBase(static::thisSite());

		foreach($shared->allFiles('') as $file){
			$stream = $shared->rawReadStream($file);
			$relativePath = basename($file);
			$path->put("base/$relativePath", $stream);
		}
	}

	/**
	 * When uploading, check if there hasnt been another person editing, then update
	 */
	public static function timeCheck(){
		$result = null;
		$time = null;
		$shared = static::disk('shared')->shiftBase(static::thisSite())->setFile('data.json');

		if ($shared->exists()){
			$data = json_decode($shared->get(), true);
			if (isset($data['updated'])) $time = \Carbon\Carbon::parse($data['updated']);
		}

		static::updateData(function($data) use ($time, &$result){
			if (isset($data['updated'])){
				$compare = \Carbon\Carbon::parse($data['updated']);
				if (!$time->eq($compare)) {
					$result = false;
					return $data;
				}
			}
			$data['updated'] = \Carbon\Carbon::now()->timestamp;
			return $data;
			
		});
		return $result ?? true;
	}

	/**
	 * Look at files in current sites locale, and save po to mo
	 */
	public static function dump(){
		$disk = static::disk('local');
		foreach((new Finder())->files()->in($disk->path('base')) as $file){
			$domain = pathinfo($file->getRelativePathname(), PATHINFO_FILENAME);
			$baseTranslations = static::fromPo($file->getRealPath());

			foreach((new Finder())->directories()->in(static::path())->exclude('base')->depth(0) as $file){

				$locale = $file->getRelativePathname();
				$disk->shiftBase($locale, 'LC_MESSAGES');
				if (!$disk->exists('')) $disk->makeDirectory('');

				if ($disk->exists($domain . ".po")){
					$targetTranslations = static::fromPo($disk->path($domain . '.po'));
					$targetTranslations = $targetTranslations->mergewith($baseTranslations, Merge::HEADERS_THEIRS | Merge::FLAGS_THEIRS | Merge::TRANSLATIONS_OURS);
				}else{
					$targetTranslations = $baseTranslations;
				}

				static::toPo($targetTranslations, $disk->path($domain . '.po'));
				static::toMo($targetTranslations, $disk->path($domain . '.mo'));
			}
		}
		
	}

}