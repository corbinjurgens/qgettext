<?php

namespace Corbinjurgens\QGetText\Concerns;

trait Tools {

	public static function thisSite(){
		return config('app.name');
	}

	public static function walkPaths(...$paths){
		$result = [];
		foreach($paths as $path){
			if (!is_string($path)){
				continue;
			}
			$explode = explode(DIRECTORY_SEPARATOR, $path);
			foreach($explode as $bit){
				if ($bit === '') continue;
				if ($bit === '.') continue;
				if ($bit === '..'){
					if (empty($result)) throw new \Exception("You can't go back any more");
					array_pop($result);
					continue;
				}
				$result[] = $bit;
			}
		}
		return join(DIRECTORY_SEPARATOR, $result);
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