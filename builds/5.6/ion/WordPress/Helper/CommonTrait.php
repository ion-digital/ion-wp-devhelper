<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper;

use \Exception as Throwable;
use WP_Post;
use DateTime;
use ion\WordPress\WordPressHelperInterface;
use ion\WordPress\Helper\Tools;
use ion\WordPress\Helper\Constants;
use ion\PhpHelper as PHP;
use ion\Package;
use ion\SemVerInterface;
use ion\SemVer;
/**
 * Description of CommonTrait*
 * @author Justus
 */
trait CommonTrait
{
    private static $scripts = [];
    private static $styles = [];
    private static $imageSizes = [];
    /**
     * method
     * 
     * @return mixed
     */
    protected static function initialize()
    {
        static::registerWrapperAction('admin_enqueue_scripts', function () {
            // Add any required scripts / styles for the back-end here
            // Colour Picker
            wp_enqueue_script("wordpresshelper_colorpicker_colorpicker", static::getHelperUri() . "assets/external/colorpicker/js/colorpicker.js", [], false, false);
            wp_enqueue_style("wordpresshelper_colorpicker_colorpicker", static::getHelperUri() . "assets/external/colorpicker/css/colorpicker.css", [], false, "screen");
            // DateTime Picker
            wp_enqueue_script("wordpresshelper_datetimepicker", static::getHelperUri() . "assets/external/datetimepicker-master/build/jquery.datetimepicker.full.js", [], false, false);
            wp_enqueue_style("wordpresshelper_datetimepicker", static::getHelperUri() . "assets/external/datetimepicker-master/jquery.datetimepicker.css", [], false, "screen");
            // WP Devhelper Admin
            wp_enqueue_style("wordpresshelper-admin", static::getHelperUri() . "assets/styles/wp-devhelper-admin.css", [], false, "screen");
            wp_enqueue_script("wordpresshelper-admin", static::getHelperUri() . "assets/scripts/wp-devhelper-admin.js", [], false, false);
            foreach (array_values(static::$scripts) as $script) {
                if ($script["backEnd"] === true && $script["inline"] === false) {
                    wp_enqueue_script($script["id"], $script["src"], $script["dependencies"], false, $script["addToEnd"]);
                }
            }
            foreach (array_values(static::$styles) as $style) {
                if ($style["backEnd"] === true && $style["inline"] === false) {
                    wp_enqueue_style($style["id"], $style["src"], $style["dependencies"], false, $style["media"]);
                }
            }
        });
        static::registerWrapperAction('wp_enqueue_scripts', function () {
            //            wp_enqueue_script('wp-devhelper', static::getHelperDirectory() . "assets/js/wp-devhelper.js", [], false, false);
            //
            //            wp_add_inline_script('wp-devhelper', "alert('DEVHELPER');");
            foreach (array_values(static::$scripts) as $script) {
                if ($script["frontEnd"] === true && $script["inline"] === false) {
                    wp_enqueue_script($script["id"], $script["src"], $script["dependencies"], $script['version'], $script["addToEnd"]);
                }
            }
            foreach (array_values(static::$styles) as $style) {
                if ($style["frontEnd"] === true && $style["inline"] === false) {
                    wp_enqueue_style($style["id"], $style["src"], $style["dependencies"], $style['version'], $style["media"]);
                }
            }
        });
        static::registerWrapperAction('wp_head', function () {
            $ajaxUrl = static::getAjaxUrl();
            $domain = parse_url(static::getSiteUri(true), PHP_URL_HOST);
            $path = parse_url(static::getSiteUri(false), PHP_URL_PATH);
            echo <<<JS
<script id="wp-devhelper" type="text/javascript">
var ajaxurl;
ajaxurl = '{$ajaxUrl}'; // deprecated!
                    
var wpDevHelper = {};
    
(function () {
          
    this.getAjaxUrl  = function() {
                    
         return '{$ajaxUrl}';
    },
    this.getDomain = function() {
         
         return '{$domain}';
    },
    this.getPath = function() {
         
         return '{$path}';
    }
         
}).apply(wpDevHelper);
                    
</script>
JS;
        }, 0);
        static::registerWrapperAction('wp_head', function () {
            foreach (array_values(static::$scripts) as $script) {
                if ($script["frontEnd"] === true && $script["inline"] === true && $script["addToEnd"] === false) {
                    echo "<script id=\"" . $script["id"] . "\" type=\"text/javascript\"><!--\n" . $script["src"] . "\n--></script>\n";
                }
            }
            foreach (array_values(static::$styles) as $style) {
                if ($style["frontEnd"] === true && $style["inline"] === true) {
                    echo "<style id=\"" . $style["id"] . "\" type=\"text/css\" media=\"" . $style["media"] . "\">\n" . $style["src"] . "\n</style>\n";
                }
            }
        }, 2);
        static::registerWrapperAction('wp_footer', function () {
            foreach (array_values(static::$scripts) as $script) {
                if ($script["frontEnd"] === true && $script["inline"] === true && $script["addToEnd"] === true) {
                    echo "<script id=\"" . $script["id"] . "\" type=\"text/javascript\"><!--\n" . $script["src"] . "\n--></script>\n";
                }
            }
        });
        static::registerWrapperAction('admin_head', function () {
            foreach (array_values(static::$scripts) as $script) {
                if ($script["backEnd"] === true && $script["inline"] === true && $script["addToEnd"] === false) {
                    echo "<script id=\"" . $script["id"] . "\" type=\"text/javascript\"><!--\n" . $script["src"] . "\n--></script>\n";
                }
            }
            foreach (array_values(static::$styles) as $style) {
                if ($style["backEnd"] === true && $style["inline"] === true) {
                    echo "<style id=\"" . $style["id"] . "\" type=\"text/css\" media=\"" . $style["media"] . "\">\n" . $style["src"] . "\n</style>\n";
                }
            }
        });
        static::registerWrapperAction('admin_footer', function () {
            foreach (array_values(static::$scripts) as $script) {
                if ($script["backEnd"] === true && $script["inline"] === true && $script["addToEnd"] === true) {
                    echo "<script id=\"" . $script["id"] . "\" type=\"text/javascript\"><!--\n" . $script["src"] . "\n--></script>\n";
                }
            }
        });
        static::registerWrapperAction('init', function () {
            add_filter('wp_setup_nav_menu_item', function ($wpMenuItem) {
                $locations = get_nav_menu_locations();
                foreach (static::$settingsMenuFields as $menuId => $fields) {
                    if (!property_exists($wpMenuItem, 'meta')) {
                        $wpMenuItem->meta = [];
                    }
                    if (!array_key_exists($menuId, $locations) && !PHP::isEmpty($menuId)) {
                        continue;
                    }
                    $menus = PHP::isEmpty($menuId) ? array_keys($locations) : $menuId;
                    foreach ($menus as $affectedMenuId) {
                        $wpMenu = wp_get_nav_menu_object($locations[$affectedMenuId]);
                        if ($wpMenu === false) {
                            continue;
                        }
                        //TODO: Check if this item is part of the menu
                        foreach ($fields as $field) {
                            //                            if($wpMenuItem->ID === 17) {
                            //                                echo "<pre>";
                            //                                var_dump((int) $wpMenuItem->ID);
                            //                                var_dump(static::getPostOption($field['name'], (int) $wpMenuItem->ID, null));
                            //                                echo "</pre>";
                            //                            }
                            $wpMenuItem->meta[$field['name']] = static::getPostOption($field['name'], (int) $wpMenuItem->ID, null);
                        }
                    }
                }
                return $wpMenuItem;
            });
        });
        static::registerWrapperAction('after_setup_theme', function () {
            $selectable = [];
            foreach (static::$imageSizes as $name => $imageSize) {
                add_image_size($imageSize['name'], $imageSize['height'], $imageSize['width'], $imageSize['crop']);
                if ($imageSize['selectable'] === true) {
                    $selectable[$name] = $imageSize['caption'] === null ? $name : __($imageSize['caption']);
                }
            }
            add_filter('image_size_names_choose', function (array $sizes) use($selectable) {
                return array_merge($sizes, $selectable);
            });
        });
    }
    /**
     * method
     * 
     * 
     * @return mixed
     */
    private static function getUrl($url, array $controllers = null, array $parameters = null, $encodeParameters = true)
    {
        $output = $url;
        if ($controllers !== null) {
            $output .= implode('/', $controllers);
        }
        if ($parameters !== null && count(array_values($parameters)) > 0) {
            $pCnt = 0;
            $tmp = [];
            foreach ($parameters as $key => $value) {
                if (!empty($value)) {
                    $tmp[] = "{$key}=" . ($encodeParameters ? rawurlencode($value) : $value);
                    $pCnt++;
                }
            }
            if ($pCnt > 0) {
                $output .= (strpos($url, '?') === false ? '?' : '&') . implode("&", $tmp);
            }
        }
        return $output;
    }
    /**
     * method
     * 
     * 
     * @return string
     */
    public static function applyTemplate($template, array $parameters)
    {
        $output = $template;
        foreach (array_keys($parameters) as $key) {
            $value = $parameters[$key];
            $type = gettype($value);
            if ($type === "boolean" || $type === "integer" || $type === "double" || $type === "string") {
                $output = preg_replace("/{\\s*({$key})\\s*}/", $value, $output);
            } else {
                if ($type === 'object') {
                    $output = preg_replace("/{\\s*({$key})\\s*}/", get_class($value), $output);
                } else {
                    if ($type === 'NULL') {
                        $output = preg_replace("/{\\s*({$key})\\s*}/", '', $output);
                    }
                }
            }
            //TODO: Revisit this?
            //else {
            //
            //    $output = (gettype($value) == 'object' ? get_class($value) : gettype($value));
            //}
        }
        return $output;
    }
    /**
     * method
     * 
     * 
     * @return mixed
     */
    public static function redirect($url, array $parameters = null, $status = null)
    {
        if ($status === null) {
            $status = 302;
        }
        //die(static::getUrl($url, null, $parameters, false));
        wp_redirect(static::getUrl($url, null, $parameters, false));
        exit($status);
    }
    /**
     * method
     * 
     * 
     * @return string
     */
    public static function getSiteLink(array $controllers = null, array $parameters = null, $absolute = true)
    {
        $url = "/";
        if ($absolute === true) {
            $url = get_home_url(null, $url);
        }
        return static::getUrl($url, $controllers, $parameters);
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public static function addScript($id, $src, $backEnd = true, $frontEnd = false, $inline = false, $addToEnd = false, $priority = 1, SemVerInterface $version = null, array $dependencies = [])
    {
        static::$scripts[$id] = ["id" => (string) $id, "src" => (string) $src, "backEnd" => (bool) $backEnd, "frontEnd" => (bool) $frontEnd, "inline" => (bool) $inline, "addToEnd" => (bool) $addToEnd, "priority" => (int) $priority, 'version' => $version === null ? static::isDebugMode() ? (string) time() : null : $version->toString(), "dependencies" => $dependencies];
    }
    /**
     * method
     * 
     * 
     * @return bool
     */
    public static function hasScript($id)
    {
        return array_key_exists($id, static::$scripts);
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public static function addStyle($id, $src, $backEnd = true, $frontEnd = false, $inline = false, $media = "screen", $priority = 1, SemVerInterface $version = null, array $dependencies = [])
    {
        static::$styles[$id] = ["id" => (string) $id, "src" => (string) $src, "backEnd" => (bool) $backEnd, "frontEnd" => (bool) $frontEnd, "inline" => (bool) $inline, "media" => (string) $media, "priority" => (int) $priority, 'version' => $version === null ? static::isDebugMode() ? (string) time() : null : $version->toString(), "dependencies" => $dependencies];
    }
    /**
     * method
     * 
     * 
     * @return bool
     */
    public static function hasStyle($id)
    {
        return array_key_exists($id, static::$styles);
    }
    /**
     * method
     * 
     * @return bool
     */
    public static function isWordPress()
    {
        if (!defined('ABSPATH')) {
            return false;
        }
        return true;
    }
    /**
     * method
     * 
     * 
     * @return bool
     */
    public static function isAdmin($includeLoginPage = false)
    {
        if (defined('WP_HELPER_ADMIN')) {
            return constant('WP_HELPER_ADMIN');
        }
        if ($includeLoginPage === true) {
            global $pagenow;
            $tmp = in_array($pagenow, ['wp-login.php', 'wp-register.php']);
            if ($tmp === true) {
                return true;
            }
        }
        if (function_exists('is_user_logged_in')) {
            return is_admin() && is_user_logged_in();
        }
        return is_admin();
    }
    /**
     * method
     * 
     * @return bool
     */
    public static function isCustomizer()
    {
        $page = filter_input(INPUT_GET, 'customize_theme', FILTER_DEFAULT);
        if (!PHP::isEmpty($page) || defined("IFRAME_REQUEST")) {
            return true;
        }
        return false;
    }
    /**
     * method
     * 
     * @return bool
     */
    public static function hasPermalinks()
    {
        return PHP::toBool(static::getRawOption('permalink_structure') !== null);
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public static function addImageSize($name, $width = null, $height = null, $crop = null, $selectable = null, $caption = null)
    {
        static::$imageSizes[$name] = ['name' => $name, 'width' => $width === null ? 0 : $width, 'height' => $height === null ? 0 : $height, 'crop' => $crop === null ? false : true, 'selectable' => $selectable === null ? false : true, 'caption' => $caption];
        return;
    }
    /**
     * method
     * 
     * 
     * @return void
     */
    public static function exitWithCode($code)
    {
        status_header($code);
        nocache_headers();
        switch ($code) {
            case 404:
                include_once get_query_template('404');
                break;
        }
        exit;
    }
    /**
     * method
     * 
     * 
     * @return bool
     */
    public static function setCookie($name, $value, $expiryTimeStamp = null, $domain = null, $path = null, $secure = null, $httpOnly = null)
    {
        if ($secure === null) {
            $secure = false;
        }
        if ($httpOnly === null) {
            $httpOnly = false;
        }
        if (is_multisite()) {
            $blogInfo = get_blog_details(get_current_blog_id());
            $domain = $blogInfo->domain;
            $path = $blogInfo->path;
        }
        if ($domain === null) {
            $domain = (string) parse_url(static::getSiteUri(), PHP_URL_HOST);
            //$domain = $blogInfo->domain;
        }
        if ($path === null) {
            $path = '/';
        }
        return setcookie($name, $value, $expiryTimeStamp != null ? $expiryTimeStamp : 0, $path, ".{$domain}", $secure, $httpOnly);
    }
    /**
     * method
     * 
     * 
     * @return ?string
     */
    public static function getCurrentObjectType($ignoreTheLoop = false)
    {
        if (static::isAdmin()) {
            return static::getCurrentAdminObjectType();
        }
        return static::getCurrentTemplateObjectType($ignoreTheLoop);
    }
    /**
     * method
     * 
     * 
     * @return ?object
     */
    public static function getCurrentObject($ignoreTheLoop = false)
    {
        if (static::isAdmin()) {
            return static::getCurrentAdminObject();
        }
        return static::getCurrentTemplateObject($ignoreTheLoop);
    }
    /**
     * method
     * 
     * 
     * @return ?int
     */
    public static function getCurrentObjectId($ignoreTheLoop = false)
    {
        if (static::isAdmin()) {
            return static::getCurrentAdminObjectId();
        }
        return static::getCurrentTemplateObjectId($ignoreTheLoop);
    }
}