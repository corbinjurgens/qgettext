<?php

namespace Corbinjurgens\QGetText;

class QGetTextContainer
{

	const NATIVE_MODE = 1;
	const EMULATED_MODE = 2;
	
	use Concerns\LoadersAndGenerators;
	use Concerns\Paths;
	use Concerns\StartupAndLoad;
	use Concerns\Tools;
	
	public function __call($name, $arguments){
		$translator = static::startup();
		static::load($translator, $name, $arguments);
		return $translator->$name(...$arguments);
	}

	use Concerns\ScanAndSync;


}
