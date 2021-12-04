<?php

namespace Corbinjurgens\QGetText;

use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

class Blade extends LaravelBladeCompiler
{
   public function bladeCompile($string){
      return parent::compileString($string);
   }
}