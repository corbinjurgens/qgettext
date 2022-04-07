<?php

namespace Corbinjurgens\QGetText\Concerns;

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

	public static function basePath(...$paths){
		return static::path('base', ...$paths);
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
				return $this->disk->{$name}(...$arguments);
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