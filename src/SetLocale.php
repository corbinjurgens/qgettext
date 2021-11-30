<?php

namespace Corbinjurgens\QGetText;

use Closure;
use Illuminate\Http\Request;

use QGetText;


class SetLocale
{
    /**
     * Set lang, and other settings for Gettext
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
		QGetTextContainer::startup();

        return $next($request);
    }
	
}
