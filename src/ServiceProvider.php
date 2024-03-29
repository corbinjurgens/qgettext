<?php

namespace Corbinjurgens\QGetText;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

use Illuminate\Routing\Router;

class ServiceProvider extends BaseServiceProvider
{
	
	static $name = 'qgettext';
	
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // config
		$this->mergeConfigFrom(
			__DIR__.'/config/qgettext.php', 'qgettext'
		);
		$this->app->singleton(self::$name, QGetTextContainer::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
      // Publish
      $this->publishes([
        __DIR__.'/config/qgettext.php' => config_path('qgettext.php'),
      ], self::$name. '-config');

      // Console
      if ($this->app->runningInConsole()) {
        $this->commands([
          Commands\Scan::class,
        ]);
      }
		
    }
}
