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

Add middleware to Http/Kernel.php and use it at the routes you wantt to start up the gettext
```
'setlocale' => \Corbinjurgens\QGetText\SetLocale::class
```

Publish Config via

--tag=qgettext-config

Configure via Corbinjurgens\QGetText\QGetTextContainer in a provider you like such as

```
\Corbinjurgens\QGetText\QGetTextContainer::$emulated = true;// Set to emulated mode as opposed to native mode which is default
\Corbinjurgens\QGetText\QGetTextContainer::$localeGetter = function(){
	return \LaravelLocalization::getCurrentLocaleRegional();//if using mcamara/laravel-localization this is a way to get current locale in "en_US" format as is likely requirerd when using native gettext
};//Set custom locale getter if its different than just app()->getLocale()
```


## Requires



## Usage
