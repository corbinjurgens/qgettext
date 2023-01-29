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
			var $file = '';
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
			 * the path yourself
			 */
			public function setFile($path = ''){
				$this->file = $path ?? '';
				return $this;
			}
			public function clearFile(){
				$this->file = '';
				return $this;
			}

			public function relativePath(){
				return QGetTextContainer::appendToPath($this->base, $this->file);
			}

			public function __call(string $name, array $arguments){
				// Access disk directly, you will need to pass path yourself
				if (strpos($name, 'raw') === 0){
					$function = \Str::camel(substr($name, 3));
					return $this->disk->{$function}(...$arguments);
				}

				// Add file to the beginning of the arguments, with the base also prepended if necessary
				array_unshift($arguments, \QGetText::appendToPath($this->base, ($this->file ?? '')));

				// Redirect a called function to a different function on this class
				if (isset(static::$function_map[$name])){
					list($func, $args) = static::$function_map[$name];
					return $this->$func($arguments, ...$args);
				}
				return $this->disk->{$name}(...$arguments);
			}

			/**
			 * Certain called functions should be overrided
			 */
			public static $function_map = [
				'files' => ['customFiles', ['files', true]],
				'allFiles' => ['customFiles', ['allFiles', true]],
			];

			/**
			 * Get file list and wrap each in an instance of this class so its easy to 
			 * do further actions on those files
			 */
			public function customFiles($arguments, ...$options){
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