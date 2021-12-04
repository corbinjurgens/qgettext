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
  protected $signature = 'gettext:scan';

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
    $path = QGetText::scan();
    $this->info("Conplete. Results saved to " . $path);
    return 0;
  }
}