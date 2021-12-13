<?php

namespace Corbinjurgens\QGetText\Concerns;

trait Tools {

	public static function thisSite(){
		return config('app.name');
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

	public static function appendToPath(...$paths){
		$result = [];
		foreach($paths as $path){
			if (!is_string($path)){
				continue;
			}
			if ($path === ""){
				continue;
			}
			$result[] = trim($path, DIRECTORY_SEPARATOR);

		}
		return join(DIRECTORY_SEPARATOR, $result);
	}

}