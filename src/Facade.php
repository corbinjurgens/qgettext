<?php

namespace Corbinjurgens\QGetText;

use Illuminate\Support\Facades\Facade as BaseFacade;

use Corbinjurgens\QGetText\ServiceProvider as S;

class Facade extends BaseFacade {
   protected static function getFacadeAccessor() { return S::$name; }
}