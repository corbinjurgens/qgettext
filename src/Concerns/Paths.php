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

	/**
	 * --------
	 * Editor
	 * --------
	 */

	/**
	 * local, edit or shared disk
	 * extended to allow 'setBase', 'shiftBase', 'setFile' and 'clearFile'
	 */
	public static function disk(){
		return new class(\Storage::disk(config('qgettext.disk')), config('qgettext.folder', '')) {
			var $disk;
			var $root = '';// original working folder
			var $base = '';// current working folder
			var $path;// path
			public function __construct($disk, $root = '')
			{
				$this->disk = $disk;
				$this->root = $root ?? '';
				$this->setBase($this->root);
			}

			public function getDisk(){
				return $this->disk;
			}

			public function clone(){
				return clone $this;
			}

			public function setBase($path = ''){
				$this->base = $path;
				return $this;
			}

			/**
			 * Move somewhere built from the original folder
			 * Use / at start or pass no path to go back work back from the original location
			 */
			public function cd(...$paths){
				if (isset($this->path)) throw new \Exception('You cannot cd off a file');
				if (empty($paths)) $paths = [''];
				$first = $paths[0];
				if (strpos($first, '/') === 0){
					return $this->clone()->clearFile()->setBase(QGetTextContainer::walkPaths($this->root, ...$paths));
				}else{
					return $this->clone()->clearFile()->setBase(QGetTextContainer::walkPaths($this->base, ...$paths));
				}


			}

			/**
			 * The target file, if set it will be used when calling all disk functions, otherwise you need to pass
			 * the path yourself
			 */
			public function setFile($path = null){
				$this->path = $path;
				return $this;
			}
			public function clearFile(){
				$this->path = null;
				return $this;
			}

			public function relativePath(){
				return QGetTextContainer::appendToPath($this->base, $this->path);
			}

			public function leafPath(){
				return $this->path;
			}

			public function __call(string $name, array $arguments){
				if (isset($this->path)){
					// Add file to the beginning of the arguments, with the base also prepended if necessary
					array_unshift($arguments, QGetTextContainer::appendToPath($this->base, ($this->path ?? '')));
				}else{
					if (empty($arguments)) $arguments = [''];
					$arguments[0] = QGetTextContainer::appendToPath($this->base, $arguments[0] ?? '');
				}

				// Redirect a called function to a different function on this class
				if (isset(static::$function_map[$name])){
					list($func, $arg) = static::$function_map[$name];
					return $this->$func($arguments, $name, $arg);
				}
				return $this->disk->{$name}(...$arguments);
			}

			/**
			 * Certain called functions should be overrided
			 */
			public static $function_map = [
				'files' => ['customFiles', true],
				'allFiles' => ['customFiles', true],
				'directories' => ['customFiles', false],
				'allDirectories' => ['customFiles', false],
			];

			/**
			 * Get file list and wrap each in an instance of this class so its easy to 
			 * do further actions on those files
			 */
			public function customFiles($arguments, $func, $arg){
				$res = $this->disk->{$func}(...$arguments);
				$base = $this->relativePath();
				$base_count = strlen($base) ? count(explode(DIRECTORY_SEPARATOR, $base)) : 0;
				return array_map(function($item) use ($base_count, $arg){
					if ($arg){
						// is file
						$item_path = explode(DIRECTORY_SEPARATOR, $item);
						$end_path = array_slice($item_path, $base_count);
						return $this->clone()->setFile(join(DIRECTORY_SEPARATOR, $end_path));
					}else{
						// if folder
						$item_path = explode(DIRECTORY_SEPARATOR, $item);
						$end_path = array_slice($item_path, $base_count);
						return $this->cd(join(DIRECTORY_SEPARATOR, $end_path));
					}
				}, $res);
			}
		};
	}

	/**
	 * Get disk, and function to make a leaf
	 * list($path, $leaf)
	 */
	public static function diskTool(...$paths){
		$path = static::appendToPath(config('qgettext.folder'), ...$paths);
		$substrfull = strlen($path);
		if ($substrfull) $substrfull++;
		$leaf = function($path) use ($substrfull){
			return substr($path, $substrfull);
		};
		return [$path, $leaf];

	}
}