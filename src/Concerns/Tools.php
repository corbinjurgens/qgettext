<?php

namespace Corbinjurgens\QGetText\Concerns;

trait Tools {

	public static function thisSite(){
		return config('app.name');
	}
	
	public static function appendToPath(...$paths){
		$result = [];
		foreach($paths as $path){
			if (!is_string($path)){
				continue;
			}
			if ($path === "" || $path === DIRECTORY_SEPARATOR){
				continue;
			}
			$result[] = trim($path, DIRECTORY_SEPARATOR);

		}
		return join(DIRECTORY_SEPARATOR, $result);
	}

}