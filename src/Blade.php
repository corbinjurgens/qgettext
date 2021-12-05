<?php

namespace Corbinjurgens\QGetText;

use Illuminate\View\Compilers\BladeCompiler as LaravelBladeCompiler;

class Blade extends LaravelBladeCompiler
{
   use Concerns\CustomScanner;

   public function __construct(){

      parent::__construct(app('files'), config('view.compiled'));
   }

   public function scanFile(string $filename){
      $template_str = \File::get($filename);
      $template_compiled = parent::compileString($template_str);
      $this->phpScanner->scanString($template_compiled, $filename);
   }
}