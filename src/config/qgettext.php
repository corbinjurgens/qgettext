<?php
return [
	'source_locale' => 'en_GB',
	'default_domain' => 'messages',
	'path' => resource_path('locale'),// without trailing slash
	'domains' => [
		'messages',
		'db'
	],
	'scan' => [
		'in' => [
			base_path('app'), 
			base_path('resources'),
			base_path('routes')
		],
		'pattern' => [
			'*.php'
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
		]
	],

	
];
