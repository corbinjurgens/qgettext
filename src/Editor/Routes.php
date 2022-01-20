<?php

namespace Corbinjurgens\QGetText\Editor;

use Illuminate\Support\Facades\Route;
trait Routes {

	public static function routes(){
		if (!config('qgettext.editor')){
			throw new \Exception("You must set config 'editor' to true, and run migrations to use routes");
		}

		Route::prefix('/qgettext')->name('qgettext.')->group(function(){
			Route::get('/', function(){

				static::sync();/////test

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