<?php

namespace Corbinjurgens\QGetText\Concerns;

use Corbinjurgens\QGetText\QGetTextContainer;

/** 
 * Depends on Tools trait
 */
trait Paths {
	
	/**
	 * --------
	 * User
	 * --------
	 */

	/** 
	 * Simply the locale cache path to read locales from
	 */
	public static function path(...$paths){
		return DIRECTORY_SEPARATOR . static::appendToPath(
			config('qgettext.path', resource_path('locale')),
			...$paths
		);
	}

	protected static $localDisk;

	/**
	 * Local Path but as a disk for easy access
	 */
	public static function localDisk(){
		if (isset(static::$localDisk)){
			return static::$localDisk;
		}
		return static::$localDisk = \Storage::build([
			'driver' => 'local',
			'root' => config('qgettext.path', resource_path('locale'))
		]);
	}

	/**
	 * --------
	 * Editor
	 * --------
	 */

	/**
	 * local, edit or shared disk
	 * extended to allow 'setBase', 'shiftBase', 'setFile' and 'clearFile'
	 */
	public static function disk($disk = "shared"){
		$_disk = $disk == "local" ? static::localDisk() : \Storage::disk(config('qgettext.' . $disk . '_path.0'));
		$class = new class {
			var $type;
			var $disk;
			var $base = '';
			var $file;
			public function clone(){
				return clone $this;
			}

			public function setBase($path = ''){
				$this->base = $path;
				return $this;
			}

			/**
			 * The normal disk base, plus deeper
			 */
			public function shiftBase(...$paths){
				return $this->setBase(\QGetText::diskPath($this->type, ...$paths));
			}

			/**
			 * The target file, if set it will be used when calling all disk functions, otherwise you need to pass
			 */
			public function setFile($path = null){
				$this->file = $path;
				return $this;
			}
			public function clearFile(){
				unset($this->file);
				return $this;
			}

			public function relativePath(){
				return QGetTextContainer::appendToPath($this->base, $this->file);
			}

			public function __call(string $name, array $arguments){
				if (strpos($name, 'raw') === 0){
					$function = \Str::camel(substr($name, 3));
					return $this->disk->{$function}(...$arguments);
				}
				// If using file, dont need to pass first argument
				if (isset($this->file)){
					array_unshift($arguments, $this->file);
				}
				if (isset($arguments[0])){
					$arguments[0] = \QGetText::appendToPath($this->base, ($arguments[0] ?? ''));
				}
				if (isset(static::$function_map[$name])){
					list($func, $arg) = static::$function_map[$name];
					//dd($func, $arg);
					return $this->$func($name, $arguments, ...$arg);
				}
				return $this->disk->{$name}(...$arguments);
			}

			public static $function_map = [
				'_files' => ['customFiles', ['files', true]],
				'_allFiles' => ['customFiles', ['allFiles', true]],
			];

			public function customFiles($name, $arguments, ...$options){
				list($func, $type) = $options;
				$res = $this->disk->{$func}(...$arguments);
				$base = $this->relativePath();
				$base_count = strlen($base) ? count(explode(DIRECTORY_SEPARATOR, $base)) : 0;
				$instance = $this;
				return array_map(function($item) use ($base, $instance, $base_count){
					$item_path = explode(DIRECTORY_SEPARATOR, $item);
					$base_path = array_slice($item_path, 0, $base_count);
					$end_path = array_slice($item_path, $base_count);
					return $instance->clone()->setBase(join(DIRECTORY_SEPARATOR, $base_path))->setFile(join(DIRECTORY_SEPARATOR, $end_path));
				}, $res);
			}
		};

		$class->type = $disk;
		$class->disk = $_disk;
		$class->shiftBase();

		return $class;
	}

	/**
	 * local, edit or shared disk path base, free to append further items to it
	 * as declared in config 
	 */
	public static function diskPath($disk = "shared", ...$paths){
		return $disk == "local" ? static::appendToPath('', ...$paths) : static::appendToPath(
			config('qgettext.' . $disk . '_path.1'),
			...$paths
		);
	}

	/**
	 * Get disk, and function to make a leaf
	 * list($path, $leaf)
	 */
	public static function diskTool($name = "shared", ...$paths){
		$path = static::diskPath($name, ...$paths);
		$substrfull = strlen($path);
		if ($substrfull) $substrfull++;
		$leaf = function($path) use ($substrfull){
			return substr($path, $substrfull);
		};
		return [$path, $leaf];

	}
}