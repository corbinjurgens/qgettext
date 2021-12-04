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

Configure via Corbinjurgens\QGetText\QGetTextContainer in a provider you like such as

```
\Corbinjurgens\QGetText\QGetTextContainer::$emulated = true;// Set to emulated mode (my personal preference) as opposed to native mode (default but requires gettext installed)
\Corbinjurgens\QGetText\QGetTextContainer::$localeGetter = function($locale){
	return \LaravelLocalization::getSupportedLocales()[$locale]['regional'];//if using mcamara/laravel-localization this is a way to get the locale in "en_US" format, for example when the app locale will differ from gettext locale name as is often the case with native implementations. Or you can create your own array mapping
};//Set custom locale getter if its different than just app()->getLocale()
```


## Requires



## Usage
