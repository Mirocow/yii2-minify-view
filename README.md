Yii 2 Minify View Component
===========================

Installation
------------
Add in `composer.json`:
```
{
    "require": {
        "Mirocow/yii2-minify-view": "*"
    }
}
```

Configure
-----
```php
<?
return [
	// ...
	'components' => [
		// ...
		'view' => [
			'class' => '\mirocow\minify\View',
			'base_path' => '@app/web', // path alias to web base
			'minify_path' => '@app/web/minify', // path alias to save minify result
			'force_charset' => 'UTF-8', // charset forcibly assign, otherwise will use all of the files found charset
			'expand_imports' => true, // whether to change @import on content
		]
	]
];
```
