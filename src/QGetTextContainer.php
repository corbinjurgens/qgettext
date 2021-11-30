<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Gettext\Loader\MoLoader;
use Gettext\Translator;
use Gettext\GettextTranslator;

class QGetTextContainer
{
	static $emulated = false;

	static $locale;
	/**
	 * Set up current locale ready for using ggettext
	 */
	public static function startup(){
		static::$locale = static::getLocale();
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

	static $translators = [];
	/**
	 * Use native gettext php functions
	 * Requires gettext installed, and is restrictive as you must also install
	 * the locale to the server, and ensure it matches exactly
	 */
	public static function startupNative($locale = null){
		$locale = $locale ?? static::$locale ?? static::getLocale();
		if (isset(static::$translators[$locale])){
			return;
		}
		$source = config('qgettext.source_locale');
		if ($locale !== $source){
			$translator = new GettextTranslator($locale);
			$default = static::getDefaultDomain();
			foreach(config('qgettext.domains') as $domain => $options){
				$translator->loadDomain($domain, ($options['path'] ?? config('qgettext.path')), $domain == $default);
			}
		}
		static::$translators[$locale] = $translator;
	}

	/**
	 * Load the translations freely without the drama
	 * For now it loads all domains. But later it could be changed to load as needed
	 */
	public static function startupEmulated($locale = null, $load_domain = null){
		$locale = $locale ?? static::$locale ?? static::getLocale();
		if (isset(static::$translators[$locale])){
			return;
		}
		$loaded = [];

		$source = config('qgettext.source_locale');
		if ($locale !== $source){
			$loader = new MoLoader();
			foreach(config('qgettext.domains') as $domain => $options){
				$loading = $loader->loadFile(($options['path'] ?? config('qgettext.path')) . "/" . $locale . "/LC_MESSAGES/" . $domain . ".mo");
				$loading->setDomain($domain);
				$loaded[] = $loading;
			}
		}
		$translator = Translator::createFromTranslations(...$loaded);
		$translator->defaultDomain(static::getDefaultDomain());
		static::$translators[$locale] = $translator;
	}
	
	public function __call($name, $arguments){
		$translator = static::$translators[static::$locale];
		if (!isset($translator)){
			throw new \Exception("You havent started gettext. Add QGetText::startup() somewhere or use Corbinjurgens\QGetText\SetLocale middleware");
		}
		return $translator->$name(...$arguments);
	}
}
