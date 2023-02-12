<?php

namespace Corbinjurgens\QGetText\Commands;

use Illuminate\Console\Command;
use Corbinjurgens\QGetText\QGetTextContainer as QGetText;

class Scan extends Command
{
	
  /**
   * The name and signature of the console command.
	 * sudo -u apache php artisan lang:merge
   *
   * @var string
   */
  protected $signature = 'gettext {mode=dump}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Scan current apps source code for gettext translations as configured in qgettext';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
      parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {

    $mode = $this->argument('mode');
    if ($mode == "scan"){
      $path = QGetText::scan($this);
      if ($path){
        $this->info("Complete");
      }
    }else if ($mode == "sync"){
      //QGetText::sync();// TODO
      QGetText::sync($this);
    }else if ($mode == "dump"){
      QGetText::dump();
      $this->info("Po converted to Mo");
    }
    
    return 0;
  }
}
