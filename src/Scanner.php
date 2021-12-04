<?php

namespace Corbinjurgens\QGetText;

class Scanner extends \Gettext\Scanner\PhpScanner
{
   public function setFunctions($functions){
      $this->functions = $functions;
   }
}