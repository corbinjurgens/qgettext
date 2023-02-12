<?php

namespace Corbinjurgens\QGetText;

class QGetTextContainer
{

	const NATIVE_MODE = 1;
	const EMULATED_MODE = 2;
	
	use Concerns\LoadersAndGenerators;
	use Concerns\StartupAndLoad;
	use Concerns\Tools;
	use Concerns\ScanAndSync;
	
	public function __call($name, $arguments){
		$translator = static::startup();
		static::load($translator, $name, $arguments);
		return $translator->$name(...$arguments);
	}

}
