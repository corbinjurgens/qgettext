<?php
return [
	'source_locale' => 'en_US',// the language used throughout your html and code
	'locales' => [
		'en_US',
		// add more here
	],
	'default_domain' => 'messages',// default domain from the list of domains
	'domains' => [
		'messages'
	],

	// Whether to use "emulated" mode which uses a different package to manage gettext,
	// or to use "native" mode which uses php's built in gettext functions
	// Emulated is the default because native causes issues sometimes
	// 	(such as needing to have the language installed on linux for it to work, or the correct language not showing)
	'mode' => Corbinjurgens\QGetText\QGetTextContainer::EMULATED_MODE,
	'max_emulated' => 3,// Max number of loaded languages at one time to prevent loading too many languages in memory. Only applies to emulated mode. Set 0 to have no limit.

	// Where to load the current sites translations from and were to save to when syncing
	// Note when using this package with git you should create the path folder used with some kind of gitignore so when
	// you sync you dont keep changing files
	'disk' => null,
	'folder' => 'locale',

	'scan' => [
		// folders to look to scan for text
		'in' => [
			base_path('app'), 
			base_path('resources'),
			base_path('routes')
		],
		// file extentions that require some kind of processing before scanning and rely on either php or js scanner
		'custom' => [
			'.blade.php' => \Corbinjurgens\QGetText\Blade::class,
		],
		// extensions to be processed as php files
		'php' => [
			'.php',
		],
		// extensions to be processed as js files
		'js' => [
			'.js',
			'.ts',
		],
		// what file names to look for
		'pattern' => [
			'*.php',
			'*.js',
			'*.ts',
		],
		// what custom functions in your code are you using for this gettext implementation
		// Used for the translation scanner
		'mapping' => [
			't' => 'gettext',
			'n' => 'ngettext',
			'p' => 'pgettext',
			'd' => 'dgettext',
			'dn' => 'dngettext',
			'dp' => 'dpgettext',
			'np' => 'npgettext',
			'dnp' => 'dnpgettext',
			'noop' => 'gettext',
		],
		// if js or php uses different function you may specify
		//'js_mapping' => []
		//'php_mapping' => []
	],

	
];
