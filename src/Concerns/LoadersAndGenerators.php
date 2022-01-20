<?php

namespace Corbinjurgens\QGetText\Concerns;

use Gettext\Loader\LoaderInterface;
use Gettext\Loader\PoLoader;
use Gettext\Loader\MoLoader;
use Gettext\Generator\GeneratorInterface;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;

use Gettext\Translations;

trait LoadersAndGenerators {

	public static function fromPo(string $filename, $domain = null){
		$loader = new PoLoader();
		return static::from($filename, $loader, $domain);
	}

	public static function fromMo(string $filename, $domain = null){
		$loader = new MoLoader();
		return static::from($filename, $loader, $domain);
	}

	public static function from(string $filename, LoaderInterface $loader, $domain = null){
		$res = CustomTranslations::move($loader->loadFile($filename));
		if (isset($domain)){
			$res->setDomain($domain);
		}
		return $res;
	}

	public static function toPo(Translations $translations, string $filename){
		$generator = new PoGenerator();
		return static::to($translations, $filename, $generator);
	}

	public static function toMo(Translations $translations, string $filename){
		$generator = new MoGenerator();
		return static::to($translations, $filename, $generator);
	}

	public static function to(Translations $translations, string $filename, GeneratorInterface $generator){
		return $generator->generateFile($translations, $filename);
	}
}