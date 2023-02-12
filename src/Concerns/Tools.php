<?php

namespace Corbinjurgens\QGetText\Concerns;

use Corbinjurgens\QStorage\QStorage;

trait Tools {

	/**
	 * This site's name
	 * 
	 * @return string
	 */
	public static function name(){
		return config('qgettext.name') ?? config('app.name');
	}

	/**
	 * Locale disk
	 * 
	 * @return \Corbinjurgens\QStorage\QStorage
	 */
	public static function disk(){
		return QStorage::disk(config('qgettext.disk'))->cd(config('qgettext.folder', 'locale'));
	}

}