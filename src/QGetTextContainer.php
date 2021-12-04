<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Gettext\Loader\MoLoader;
use Gettext\Translator;
use Gettext\GettextTranslator;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Events\LocaleUpdated;
use Gettext\Generator\ArrayGenerator;

class QGetTextContainer
{
	static $emulated = false;

	/**
	 * Current locale set from applicaation
	 */
	static $locale;

	static $force_locale;

	static $booted = false;

	/** Last used native locale, useed to check if current gettext locale needs to be updated via setlocale() */
	static $locale_native;

	public static function boot($locale = null){
		if (static::$booted === true){
			return;
		}
		// Listen to any later locale updates
		Event::listen(function(LocaleUpdated $event){
			static::setLocale($event->locale);
		});
		static::setLocale($locale ?? app()->getLocale());
		static::$booted = true;
	}

	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup($locale = null){
		static::boot($locale);
		return static::$emulated ? static::startupEmulated($locale) : static::startupNative($locale);

	}

	/**
	 * Set to a custom closure if you want to get locale from other than app()->getLocale();
	 */
	public static $localeGetter;

	/**
	 * Update locale
	 */
	public static function setLocale($locale){
		if (isset(static::$localeGetter)){
			return static::$locale = (static::$localeGetter)($locale);
		}
		return static::$locale = $locale;
	}

	public static function getLocale(){
		return static::$force_locale ?? static::$locale;
	}

	public static function getDefaultDomain(){
		return config('qgettext.default_domain', 'messages');
	}

	static $translator;

	static $translators_emulated = [];
	/**
	 * Use native gettext php functions
	 * Requires gettext installed, and is restrictive as you must also install
	 * the locale to the server, and ensure it matches exactly
	 */
	public static function startupNative($locale = null){
		if (isset(static::$translator)){
			return static::$translator;
		}
		$locale = $locale ?? static::getLocale();
		$translator = new GettextTranslator($locale);
		
		$default = static::getDefaultDomain();
		$path = config('qgettext.path');
		foreach(config('qgettext.domains') as $domain){
			$translator->loadDomain($domain, $path, $domain == $default);
		}
		return static::$translator = $translator;
	}

	static $emulatedDomainArgs = [
		'dngettext' => 0,
		'dgettext' => 0,
		'dpgettext' => 0,
		'dnpgettext' => 0
	];

	/**
	 * Prepare the translations instance freely without the php drama
	 */
	public static function startupEmulated($locale = null){
		$locale = $locale ?? static::getLocale();
		if (isset(static::$translators_emulated[$locale])){
			return static::$translators_emulated[$locale];
		}
		
		$translator = new Translator();
		$translator->defaultDomain(static::getDefaultDomain());
		return static::$translators_emulated[$locale] = $translator;
	}

	public static function load($translator, $name, $arguments){
		static::$emulated ? static::loadEmulated($translator, $name, $arguments) : static::loadNative($translator, $name, $arguments);
	}

	public static function loadNative($translator, $name, $arguments){
		// If language is not yet used or changed, update it
		if (static::$locale_native !== static::getLocale()){
			$translator->setLanguage(static::getLocale());
			static::$locale_native = static::getLocale();
		}
	}

	static $loaded_emulated = [];

	/**
	 * Only loads domains as needed
	 */
	public static function loadEmulated($translator, $name, $arguments){
		$locale = static::getLocale();
		$domain = isset(static::$emulatedDomainArgs[$name]) ? $arguments[static::$emulatedDomainArgs[$name]] : static::getDefaultDomain();
		
		if (isset(static::$loaded_emulated[$locale][$domain])){
			return static::$loaded_emulated[$locale][$domain];
		}
		$file = config('qgettext.path') . "/" . $locale . "/LC_MESSAGES/" . $domain . ".mo";
		if (!\File::exists($file)){
			return static::$loaded_emulated[$locale][$domain] = false;
		}
		$loader = new MoLoader();

		$loading = $loader->loadFile($file);
		$loading->setDomain($domain);
		$arrayGenerator = new ArrayGenerator();
		$translator->addTranslations($arrayGenerator->generateArray($loading));
		return static::$loaded_emulated[$locale][$domain] = true;

	}
	
	public function __call($name, $arguments){
		$translator = static::startup();
		static::load($translator, $name, $arguments);
		return $translator->$name(...$arguments);
	}

	public static function scan(){

	}
}
