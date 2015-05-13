Yii 2 Minify View Component
===========================

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
php composer.phar require --prefer-dist "mirocow/yii2-minify-view" "*"
```

or add

```json
"mirocow/yii2-minify-view" : "*"
```

to the require section of your application's `composer.json` file.

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
