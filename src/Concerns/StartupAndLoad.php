<?php

namespace Corbinjurgens\QGetText\Concerns;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Events\LocaleUpdated;
use Gettext\GettextTranslator;
use Gettext\Translator;

use Gettext\Generator\ArrayGenerator;

trait StartupAndLoad {

	/**
	 * Current locale set from applicaation
	 */
	static $locale;

	static $force_locale;

	static $booted = false;

	/** Last used native locale, useed to check if current gettext locale needs to be updated via setlocale() */
	static $locale_native;

	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup($locale = null){
		static::boot($locale);
		return static::getMode() === static::EMULATED_MODE ? static::startupEmulated($locale) : static::startupNative($locale);

	}
	
	public static function getMode(){
		return config('qgettext.mode', static::NATIVE_MODE);
	}

	protected static function boot($locale = null){
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
	 * Set to a custom closure if you want to modify locale received from app()->getLocale()
	 * eg, the laravel local is called 'en' but you want to change it to 'en_US' for gettext use
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

	/**
	 * Functions that use a domain argument, 
	 * the index of the domain argument 
	 */
	static $emulatedDomainArgs = [
		'dngettext' => 0,
		'dgettext' => 0,
		'dpgettext' => 0,
		'dnpgettext' => 0
	];
	
	/** Starting up too many languages may cause memory issues. Use config qgettext.max_emulated to configure a max */
	static $started_emulated_list = [];

	/**
	 * Prepare the translations instance freely without the php drama
	 */
	public static function startupEmulated($locale = null){
		$locale = $locale ?? static::getLocale();
		if (isset(static::$translators_emulated[$locale])){
			return static::$translators_emulated[$locale];
		}
		
		static::startupEmulatedShift($locale);
		$translator = new Translator();
		$translator->defaultDomain(static::getDefaultDomain());
		return static::$translators_emulated[$locale] = $translator;
	}

	protected static function startupEmulatedShift($locale){
		static::$started_emulated_list[] = $locale;
		$limit = config('qgettext.max_emulated', 0) ?? 0;

		if (!$limit) return;
		if ($limit >= count(static::$started_emulated_list)) return;

		$shited = array_shift(static::$started_emulated_list);
		unset(static::$translators_emulated[$shited]);
	}

	public static function load($translator, $name, $arguments){
		static::getMode() === static::EMULATED_MODE ? static::loadEmulated($translator, $name, $arguments) : static::loadNative($translator, $name, $arguments);
	}

	public static function loadNative($translator, $name, $arguments){
		// If language is not yet used or changed, update it
		if (!isset(static::$locale_native) || static::$locale_native !== static::getLocale()){
			$translator->setLanguage(static::getLocale());
			static::$locale_native = static::getLocale();
		}
	}

	/** @var array flag for if the locale and domain is loaded */
	static $loaded_emulated = [];

	/**
	 * Only loads domains as needed
	 * @return bool
	 */
	public static function loadEmulated($translator, $name, $arguments){
		$locale = static::getLocale();
		$domain = isset(static::$emulatedDomainArgs[$name]) ? $arguments[static::$emulatedDomainArgs[$name]] : static::getDefaultDomain();
		
		if (isset(static::$loaded_emulated[$locale][$domain])){
			return static::$loaded_emulated[$locale][$domain];
		}
		$file = config('qgettext.path') . DIRECTORY_SEPARATOR . $locale . "/LC_MESSAGES/" . $domain . ".mo";
		if (!\File::exists($file)) return static::$loaded_emulated[$locale][$domain] = false;

		$loading = static::fromMo($file, $domain);
		$arrayGenerator = new ArrayGenerator();
		$translator->addTranslations($arrayGenerator->generateArray($loading));
		return static::$loaded_emulated[$locale][$domain] = true;

	}
}