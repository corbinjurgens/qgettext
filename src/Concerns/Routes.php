<?php

namespace Corbinjurgens\QGetText\Concerns;

use Illuminate\Support\Facades\Route;
trait Routes {

	public static function loadRoutes(){
		if (!config('qgettext.editor')){
			throw new \Exception("You must set config 'editor' to true, and run migrations to use routes");
		}

		Route::prefix('/qgettext')->name('qgettext.')->group(function(){
			Route::get('/', function(){
				return view('qgettext::home');
			})->name('home');

			Route::get('/sync', function(){

				return back();
			});



			Route::prefix('/sites')->group(function(){
				Route::get('/', function(){
					
				})->name('sites');

			});

		});

	}

}