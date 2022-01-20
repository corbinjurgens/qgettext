<?php

namespace Corbinjurgens\QGetText\Concerns;

/** 
 * Depends on Tools trait
 */
trait Paths {
	
	/**
	 * ===
	 * CURRENT SITE
	 * ===
	 */

	/** 
	 * Simply the locale cache to read locales from
	 */
	public static function path(...$paths){
		return DIRECTORY_SEPARATOR . static::appendToPath(
			config('qgettext.path', resource_path('locale')),
			...$paths
		);
	}

	protected static $pathDisk;

	/**
	 * Path but as a disk for easy access
	 */
	public static function pathDisk(){
		if (isset(static::$pathDisk)){
			return static::$pathDisk;
		}
		return static::$pathDisk = \Storage::build([
			'driver' => 'local',
			'root' => config('qgettext.path', resource_path('locale'))
		]);
	}

	public static function basePath(...$paths){
		return static::path('base', ...$paths);
	}

	/**
	 * ===
	 * OTHER SITES
	 * ===
	 */

	/**
	 * edit or shared disk
	 */
	public static function disk($disk = "shared"){
		return \Storage::disk(config('qgettext.' . $disk . '_path.0'));
	}

	public static function diskPath($disk = "shared", ...$paths){
		return static::appendToPath(
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