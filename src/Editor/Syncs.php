<?php

namespace Corbinjurgens\QGetText\Editor;

/**
 * Depends on Paths, LoadersAndGenerators trait
 */
trait Syncs {

	public static function sync(){
		$disk = static::disk("shared");
		list($path, $leaf) = static::diskTool("shared");
		$sites = $disk->directories($path);
		foreach($sites as $path){
			$site = $leaf($path);
			static::syncSite($site);
		}
	}

	public static function syncSite($site){
		$disk = static::disk("shared");
		list($path, $leaf) = static::diskTool("shared", $site, "base");

		$editDisk = static::disk("edit");
		$domains = $disk->files($path);
		foreach($domains as $domain_path){
			$domain = $leaf($domain_path);
			if (!\Str::endsWith($domain, ".po")){
				continue;
			}

			list($pathEdit, $leafEdit) = static::diskTool("edit", $site, "base", $domain);
			$editDisk->put($pathEdit, $disk->readStream($domain_path));
			$domain_name = basename($domain, ".po");

			$translations = static::fromPo($editDisk->path($pathEdit), $domain_name);
			dd($translations, "?");

			dd($domain_name);
			$site = basename($path);
			static::syncSite($site);
		}

	}

}