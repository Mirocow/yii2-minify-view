<?php
/**
 * View.php
 * @author Revin Roman http://phptime.ru
 */

namespace mirocow\minify;

use mirocow\minify\Minify_HTML;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use JSMin\JSMin;
use Yii;

class View extends \yii\web\View
{

    /**
     * @var string path alias to web base
     */
    public $base_path = '@app/web';

    /**
     * @var string path alias to save minify result
     */
    public $minify_path = '@app/web/minify';

    /**
     * @var bool|string charset forcibly assign, otherwise will use all of the files found charset
     */
    public $force_charset = false;

    /**
     * @var bool whether to change @import on content
     */
    public $expand_imports = true;

    /**
     * @var int
     */
    public $css_linebreak_pos = 2048;

    /**
     * @var int chmod of minified file
     */
    public $file_mode = 664;

    public $js_len_to_minify = 1000; // not used

    public $minify_css = true;

    public $minify_js = true;

    public $minify_html = true;

    public $obfuscate_js = false;

    public $obfuscate_js_encoding = 'Normal';

    public $obfuscate_js_fastDecode = true;

    public $obfuscate_js_specialChars = true;

    public function init()
    {
        parent::init();
        
        if(Yii::$app->request->isConsoleRequest){
            return;
        }

        $minify_path = $this->minify_path = \Yii::getAlias($this->minify_path);
        if (!file_exists($minify_path)) {
            FileHelper::createDirectory($minify_path);
        }

        if (!is_readable($minify_path)) {
            throw new \RuntimeException(\Yii::t('app',
                'Directory for compressed assets is not readable.'));
        }

        if (!is_writable($minify_path)) {
            throw new \RuntimeException(\Yii::t('app',
                'Directory for compressed assets is not writable.'));
        }
               
    }

    public function endPage($ajaxMode = false)
    {
        $this->trigger(self::EVENT_END_PAGE);

        $content = ob_get_clean();
        foreach (array_keys($this->assetBundles) as $bundle) {
            $this->registerAssetFiles($bundle);
        }

        if (!YII_DEBUG) {
          $this->minify();
        }

        $content = strtr($content, [
            self::PH_HEAD => $this->renderHeadHtml(),
            self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
            self::PH_BODY_END => $this->renderBodyEndHtml($ajaxMode),
        ]);

        if($this->minify_html) {
            echo $this->minifyHTML($content);
        } else {
            echo $content;
        }

        $this->clear();
    }

    public function clear()
    {
        $this->metaTags = null;
        $this->linkTags = null;
        $this->css = null;
        $this->cssFiles = null;
        $this->js = null;
        $this->jsFiles = null;
        $this->assetBundles = [];
    }

    protected function registerAssetFiles($name)
    {
        if (!isset($this->assetBundles[$name])) {
            return;
        }

        $bundle = $this->assetBundles[$name];
        if ($bundle) {
            foreach ($bundle->depends as $dep) {
                $this->registerAssetFiles($dep);
            }

            $bundle->registerAssetFiles($this);
        }

        unset($this->assetBundles[$name]);
    }

    private function minify()
    {
        if ($this->minify_css) {
            $this->minifyCSS();
        }

        if ($this->minify_js) {
            $this->minifyJS();
        }
    }

    /**
     * @return self
     */
    private function minifyCSS()
    {
        if (!empty($this->cssFiles)) {
            $css_files = array_keys($this->cssFiles);

            $long_hash = '';
            foreach ($css_files as $file) {
              
                $file = \Yii::getAlias($this->base_path) . $file;
                if (!is_file($file)) {
                    continue;
                }                
                $hash = sha1_file($file);
                $long_hash .= $hash;
                
            }

            $css_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . sha1($long_hash) . '.css';
            if (!file_exists($css_minify_file)) {
                $css = '';
                $charsets = '';
                $imports = [];
                $fonts = [];
                
                foreach ($css_files as $file) {
                  
                    $file = \Yii::getAlias($this->base_path) . $file;
                    if (!is_file($file)) {
                        continue;
                    }
                    $css .= file_get_contents($file);                    
                    
                    if (preg_match_all('~\@charset[^;]+~is', $css, $m)) {
                        foreach ($m[0] as $k => $v) {
                            $string = $m[0][$k] . ';';
                            $css = str_replace($string, '', $css);
                            if (false === $this->force_charset) {
                                $charsets .= $string . PHP_EOL;
                            }
                        }
                    }

                    if (preg_match_all('~\@import[^;]+~is', $css, $m)) {
                        foreach ($m[0] as $k => $v) {
                            $string = $m[0][$k] . ';';
                            $key = md5($string);
                            if(empty($imports[$key])){
                              $imports[$key] = $string;
                            }
                            $css = str_replace($string, '', $css);
                        }
                    }

                    if (preg_match_all('~\@font-face\s*\{[^}]+\}~is', $css, $m)) {
                        foreach ($m[0] as $k => $v) {
                            $string = $m[0][$k];
                            $key = md5($string);
                            if(empty($fonts[$key])){
                              $string = preg_replace_callback('~url\([\'"](.*?)[?#\'"]\)~is', function($matches) use ($file){
                                $assets_path = str_replace(\Yii::getAlias($this->base_path), '', dirname($file));
                                return 'url(\'' . $assets_path . '/' . $matches[1] . '\')';
                              },
                              $string);
                              $fonts[$key] = $string;
                            }
                            $css = str_replace($string, '', $css);
                        }
                    }                    
                    
                }                

                if (true === $this->expand_imports) {
                    preg_match_all('|\@import\s([^;]+);|is', str_replace('&amp;', '&', $css), $m);
                    if (!empty($m[0])) {
                        foreach ($m[0] as $k => $v) {
                            $import_url = $m[1][$k];
                            if (!empty($import_url)) {
                                $import_content = $this->getImportContent($import_url);
                                if (!empty($import_content)) {
                                    $css = str_replace($m[0][$k], $import_content, $css);
                                }
                            }
                        }
                    }
                }
                
                // remove comments
                $css = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $css);                

                $css = (new \CSSmin())->run($css, $this->css_linebreak_pos);

                if (false !== $this->force_charset) {
                    $charsets = '@charset "' . (string) $this->force_charset . '";' . PHP_EOL;
                }

                $css = $charsets . implode(PHP_EOL, $imports) . implode(PHP_EOL, $fonts) . $css;

                file_put_contents($css_minify_file, $css);
                chmod($css_minify_file, octdec($this->file_mode));
            }

            $css_file = str_replace(\Yii::getAlias($this->base_path), '', $css_minify_file);
                
            $this->cssFiles = [$css_file => Html::cssFile($css_file)];
        }

        return $this;
    }

    /**
     * @return self
     */
    private function minifyJS()
    {
        if (!empty($this->jsFiles)) {
            $only_pos = [self::POS_END];
            $js_files = $this->jsFiles;
            foreach ($js_files as $position => $files) {
                if (false === in_array($position, $only_pos)) {
                    $this->jsFiles[$position] = [];
                    foreach ($files as $file => $html) {
                        $this->jsFiles[$position][$file] = Html::jsFile($file);
                    }
                } else {
                    $this->jsFiles[$position] = [];

                    $long_hash = '';
                    foreach ($files as $file => $html) {
                      
                        $file = \Yii::getAlias($this->base_path) . $file;
                        if (!is_file($file)) {
                            continue;
                        }
                        
                        $hash = sha1_file($file);
                        $long_hash .= $hash;
                    }

                    $js_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . sha1($long_hash) . '.js';
                    if (!file_exists($js_minify_file)) {
                        $js = '';
                        foreach ($files as $file => $html) {
                            $source_file = $file;
                            $file = \Yii::getAlias($this->base_path) . $source_file;
                            if (!is_file($file)) {
                                continue;
                            }
                            $code = file_get_contents($file) . ';';
                            if ($this->obfuscate_js) {
                                $code = (new \GK\JavascriptPacker($code,
                                    $this->obfuscate_js_encoding,
                                    $this->obfuscate_js_fastDecode,
                                    $this->obfuscate_js_specialChars))->pack();
                            }
                            $code = preg_replace('#(?:[^\*]|^)(\/\*(?:[^*]*(?:\*(?!\/))*)*\*\/)#', '', $code);
                            $code = (new JSMin($code))->min();
                            $js .= "/*! *****************************\n source: {$source_file}\n***************************** */\n" . $code . PHP_EOL;
                        }

                        file_put_contents($js_minify_file, $js);
                        chmod($js_minify_file, octdec($this->file_mode));
                    }

                    // Include remote file
                    foreach ($files as $file => $html) {
                        if (preg_match('~^https*://~', $file)) {
                            $this->jsFiles[$position][$file] = $html;
                            continue;
                        }
                    }

                    $js_file = str_replace(\Yii::getAlias($this->base_path), '',
                        $js_minify_file);
                    $this->jsFiles[$position][$js_file] = Html::jsFile($js_file . '?t=' . filemtime($js_minify_file));
                }
            }
        }

        $ready = [];
        $load = [];

        if (!empty($this->js)) {
            foreach ($this->js as $position => &$codes) {
                foreach ($codes as &$code) {

                    if (strlen($code) > $this->js_len_to_minify) {

                        if ($position <> self::POS_READY) {

                            $js_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . sha1($code) . '.js';

                            if (!file_exists($js_minify_file)) {
                                if ($this->obfuscate_js) {
                                    $code = (new \GK\JavascriptPacker($code,
                                        $this->obfuscate_js_encoding,
                                        $this->obfuscate_js_fastDecode,
                                        $this->obfuscate_js_specialChars))->pack();
                                }
                                $code = preg_replace('#(?:[^\*]|^)(\/\*(?:[^*]*(?:\*(?!\/))*)*\*\/)#', '', $code);
                                $code = (new JSMin($code))->min();
                                file_put_contents($js_minify_file, $code);
                                chmod($js_minify_file,
                                    octdec($this->file_mode));
                            }

                            $js_file = str_replace(\Yii::getAlias($this->base_path),
                                '', $js_minify_file);

                            $this->jsFiles[$position][$js_file] = Html::jsFile($js_file . '?t=' . filemtime($js_minify_file));

                        } elseif ($position == self::POS_LOAD) {

                            $load[] = $code;

                        } else {

                            $ready[] = $code;

                        }

                        unset($this->js[$position]);

                    } else {

                        if ($position <> self::POS_READY) {

                        } elseif ($position == self::POS_LOAD) {

                            $load[] = $code;

                        } else {

                            $ready[] = $code;

                        }

                    }

                }
            }

            if ($ready) {

                $inline_code = implode("\n", $ready);

                $js_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . sha1($inline_code) . '.js';

                if (!file_exists($js_minify_file)) {
                    $inline_code = "jQuery(document).ready(function(){\n" . $inline_code . "\n});";
                    if ($this->obfuscate_js) {
                        $inline_code = (new \GK\JavascriptPacker($inline_code,
                            $this->obfuscate_js_encoding,
                            $this->obfuscate_js_fastDecode,
                            $this->obfuscate_js_specialChars))->pack();
                    }
                    $inline_code = preg_replace('#(?:[^\*]|^)(\/\*(?:[^*]*(?:\*(?!\/))*)*\*\/)#', '', $inline_code);
                    $inline_code = (new JSMin($inline_code))->min();
                    file_put_contents($js_minify_file, $inline_code);
                    chmod($js_minify_file, octdec($this->file_mode));
                }

                $js_file = str_replace(\Yii::getAlias($this->base_path), '',
                    $js_minify_file);

                $this->jsFiles[self::POS_END][$js_file] = Html::jsFile($js_file . '?t=' . filemtime($js_minify_file));

            }

            if ($load) {

                $inline_code = implode("\n", $load);

                $js_minify_file = $this->minify_path . DIRECTORY_SEPARATOR . sha1($inline_code) . '.js';

                if (!file_exists($js_minify_file)) {
                    $inline_code = "jQuery(window).load(function(){\n" . $inline_code . "\n});";
                    if ($this->obfuscate_js) {
                        $inline_code = (new \GK\JavascriptPacker($inline_code,
                            $this->obfuscate_js_encoding,
                            $this->obfuscate_js_fastDecode,
                            $this->obfuscate_js_specialChars))->pack();
                    }
                    $inline_code = preg_replace('#(?:[^\*]|^)(\/\*(?:[^*]*(?:\*(?!\/))*)*\*\/)#', '', $inline_code);
                    $inline_code = (new JSMin($inline_code))->min();
                    file_put_contents($js_minify_file, $inline_code);
                    chmod($js_minify_file, octdec($this->file_mode));
                }

                $js_file = str_replace(\Yii::getAlias($this->base_path), '',
                    $js_minify_file);

                $this->jsFiles[self::POS_END][$js_file] = Html::jsFile($js_file . '?t=' . filemtime($js_minify_file));

            }

        }

        return $this;
    }

    /**
     * @return self
     */
    private function minifyHTML($content){

        return Minify_HTML::minify($content, [
            //'cssMinifier' => function(){},
            //'jsMinifier' => function(){},
            'jsCleanComments' => true,
        ]);

    }


    private function getImportContent($url)
    {
        $result = null;

        if ('url(' === StringHelper::byteSubstr($url, 0, 4)) {
            $url = str_replace([
                'url(\'',
                'url(',
                '\')',
                ')'
            ], '', $url);

            if (StringHelper::byteSubstr($url, 0, 2) === '//') {
                $url = preg_replace('|^//|', 'http://', $url, 1);
            }

            if (!empty($url)) {
                $result = file_get_contents($url);
            }
        }

        return $result;
    }
}
