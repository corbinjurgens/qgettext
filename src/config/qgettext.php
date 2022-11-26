<?php
return [
	//--------
	// User
	//--------

	// Use a different identifier for this site to keep track of its translations. Otherwise app.name setting will be used
	// This is good if your app.name is differing between enviroments, or will change. 
	//'identifier' => 'site',

	'mode' => Corbinjurgens\QGetText\QGetTextContainer::EMULATED_MODE,
	'max_emulated' => 3,// Max number of loaded languages at one time to prevent loading too many languages in memory. Set null to have no limit.
	'source_locale' => 'en_US',
	'default_domain' => 'messages',
	// Where to load the current sites translations from and were to save to when syncing
	// Note when using this package with git you should create the path folder used with some kind of gitignore so when
	// you sync you dont keep changing files
	'path' => resource_path('locale'),// without trailing slash
	'domains' => [
		'messages',
		'db'
	],
	'scan' => [
		// file extentions that require some kind of processing before scanning and rely on either php of js scanner
		'custom' => [
			'.blade.php' => \Corbinjurgens\QGetText\Blade::class,
		],
		// extensions to be processed as php files
		'php' => [
			'.php',
		],
		// extensions to be processed as js files if you are using some kind of js framework with your laravel app
		'js' => [
			'.js',
			'.ts',
		],
		// folders to look for files
		'in' => [
			base_path('app'), 
			base_path('resources'),
			base_path('routes')
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
		//'js_mapping' => []
		//'php_mapping' => []
	],

	
];
