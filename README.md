# Introduction

This plugin can be used as a "user", "editor", or both

A user means the application scans itself, and uploads the results to the declared shared location
An editor views the resulting files and allowes editing and saving for the user to then download


# Setup
## Manual Installation

Copy the following to main composer.(in the case that the package is added to packages/corbinjurgens/qform)
```
 "autoload": {
	"psr-4": {
		"Corbinjurgens\\QGetText\\": "packages/corbinjurgens/gettext/src"
	},
},
```
and run 
```
composer dump-autoload
```


Add the following to config/app.php providers
```
Corbinjurgens\QGetText\ServiceProvider::class,
```
Add alias to config/app.php alias
```
"QGetText" => Corbinjurgens\QGetText\Facade::class,
```

Doesnt need any middleware to set locale as it will set on first use, and then listen for changes to apps locale

Publish Config via

--tag=qgettext-config

Change native mode or emulated mode in the config file. Also change scanner settings.

Will only load migrations and views if qgettext.editor is set to true

Configure via Corbinjurgens\QGetText\QGetTextContainer in a provider you like such as

```
\Corbinjurgens\QGetText\QGetTextContainer::$localeGetter = function($locale){
	return \LaravelLocalization::getSupportedLocales()[$locale]['regional'];//if using mcamara/laravel-localization this is a way to get the locale in "en_US" format, for example when the app locale will differ from gettext locale name as is often the case with native implementations. Or you can create your own array mapping
};//Set custom locale getter if its different than just app()->getLocale()
```


## Requires

```
"gettext/gettext": "^5.6",
"gettext/php-scanner": "^1.3",
"gettext/js-scanner": "^1.1",
"gettext/translator": "^1.0"
```

## Preparation

Migrate your translations to gettext, such as changing all occurences of __('Translation') to _('Translation')

Set your chosen upload location via config qgettext.shared_path. An S3 location is recommended but not required

## Usage

Run `php artisan gettext scan` and your site will be scanned, and the result will be uploaded to the shared location

TODO Use the editor to wherever it is you have it set to upload the scan results. Perhaps in the same site? Fill in your translations and other configurations

TODO Run php artisan gettext sync. On your site. The translations should now be working.

Still in development


