<?php

namespace Corbinjurgens\QGetText\Concerns;

trait CustomScanner {

   protected $phpScanner;

   protected $jsScanner;

   public function loadScanners($phpScanner, $jsScanner){
      $this->phpScanner = $phpScanner;
      $this->jsScanner = $jsScanner;
   }

   abstract public function scanFile(string $filename);
}