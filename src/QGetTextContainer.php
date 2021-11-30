<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Gettext\Loader\MoLoader;
use Gettext\Translator;

class QGetTextContainer
{
	static $emulated = false;
	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup(){
		if (static::$emulated){
			static::startupEmulated();
		}else{
			static::startupNative();
		}
        //bind_textdomain_codeset('messages', 'UTF-8');
	}

	/**
	 * Set to a custom closure if you want to get locale from other than app()->getLocale();
	 */
	public static $localeGetter;

	public static function getLocale(){
		if (isset(static::$localeGetter)){
			return (static::$localeGetter)();
		}
		return app()->getLocale();
	}

	public static function getDefaultDomain(){
		return config('qgettext.default_domain', 'messages');
	}

	/**
	 * Use native gettext php functions
	 * Requires gettext installed, and is restrictive as you must also install
	 * the locale to the server, and ensure it matches exactly
	 */
	public static function startupNative(){

		foreach(config('qgettext.domains') as $domain => $options){
			bindtextdomain($domain, $options['path'] ?? config('qgettext.path'));
		}
		textdomain(static::getDefaultDomain());
		setlocale(LC_MESSAGES, static::getLocale());
	}

	static $locale;

	static $translations = [];

	static $paths = [];

	static $domain;

	/**
	 * Load the translations freely without the drama
	 */
	public static function startupEmulated(){
		foreach(config('qgettext.domains') as $domain => $options){
			static::$paths[$domain] = ($options['path'] ?? config('qgettext.path'));
		}
		static::$locale = static::getLocale();
		static::$domain = static::getDefaultDomain();
	}
	
	public static function text(string $message){
		if (static::$emulated){
			return static::textEmulated($message);
		}else{
			return static::textNative($message);
		}
	}

	public static function textNative(string $message){
		return gettext($message);
	}

	public static function textEmulated(string $message){
		static::load();
		return static::$translations[static::$locale][static::$domain]->gettext($message);
	}

	public static function load($locale = null, $domain = null){
		$locale = $locale ?? static::$locale;
		$domain = $domain ?? static::$domain;
		if (!isset(static::$translations[$locale][$domain])){
			$loader = new MoLoader();
			static::$translations[$locale][$domain] = Translator::createFromTranslations($loader->loadFile(static::$paths[$domain] . "/" . $locale . "/LC_MESSAGES/" . $domain . ".mo"));
		}
		//dd(static::$translations, static::$paths[$domain] . "/" . $locale . "/LC_MESSAGES/" . $domain . ".mo");
	}
}
