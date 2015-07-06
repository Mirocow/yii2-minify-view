Yii 2 Minify View Component
===========================

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

### Add github repository


```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/mirocow/yii2-minify-view.git"
        }
    ]
```

and then

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
      'minify_css' => true,
      'minify_js' => true, //YII_ENV_DEV ? false : true,
      'js_len_to_minify' => 1000, // Больше этого размера inlinejs будет сжиматься и упаковываться в файл
      'force_charset' => 'UTF-8', // charset forcibly assign, otherwise will use all of the files found charset
      'expand_imports' => true, // whether to change @import on content
      //'css_linebreak_pos' => false,
      
      // Theming
      'theme' => [
        'basePath' => '@app/themes/myapp',
        'baseUrl' => '@app/themes/myapp',
        'pathMap' => [ 
          '@app/modules' => '@app/themes/myapp/modules',
          /*'@app/views' => [ 
            '@webroot/themes/myapp/views',
          ]*/
        ],
      ],          
      
    ],
	]
];
```
