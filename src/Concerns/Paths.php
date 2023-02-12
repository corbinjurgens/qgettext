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
	 * --------
	 * Editor
	 * --------
	 */

	/**
	 * Locale disk
	 */
	public static function disk(){
		return \QStorage::disk(config('qgettext.disk'))->cd(config('qgettext.folder', 'locale'));
	}
}