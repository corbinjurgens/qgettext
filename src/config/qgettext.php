<?php
return [
	'source_locale' => 'en_GB',
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
	// Storage disk and path of where to upload this sites data, and where to look when editing uploaded sites translations
	// It is highly recommended to use an s3 storage location so that you can keep your translations synced even between local and production
	'shared_path' => [
		'local',//disk name, recommend an disk which is a s3 driver but default is the local disk which a local driver
		'locale'//path inside disk
	],
	// Storage disk and path of a local path when used as the gettext editor, where to save files to for editing before finally saving to the shared_path
	'edit_path' => [
		'local',// disk name, should be a local driver disk as this is where files will be copied to and from before being uploaded to the shared_path
		'locale_editor'// path inside disk
	]

	
];
