<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper\Wrappers;

use Throwable;
use WP_Post;
use ion\WordPress\IWordPressHelper;
use ion\Types\Arrays\IMap;
use ion\Types\Arrays\Map;
use ion\Types\Arrays\IVector;
use ion\Types\Arrays\Vector;
use ion\WordPress\Helper\Tools;
use ion\WordPress\Helper\Constants;
use ion\PhpHelper as PHP;
use ion\Package;
use ion\System\File;
use ion\System\Path;
use ion\System\FileMode;
use ion\ISemVer;
use ion\SemVer;
/**
 *
 * @author Justus
 */
trait TActions
{
    private static $actions = [];
    private static $ajaxActions = [];
    private static $formActions = [];
    /**
     * method
     * 
     * @return mixed
     */
    
    protected static function initialize_TActions()
    {
        static::registerWrapperAction('init', function () {
            foreach (array_keys(static::$actions) as $key) {
                foreach (static::$actions[$key] as $action) {
                    add_action($key, $action['function'], $action['priority']);
                }
            }
        });
        // NOTE: admin-post.php and admin-ajax.php don't seem to fire 'init', so
        // both front-end and back-end hooks get created on 'admin_init'
        static::registerWrapperAction('admin_init', function () {
            foreach (static::$ajaxActions as $action) {
                if ($action['frontEnd'] === true) {
                    add_action('wp_ajax_nopriv_' . $action['name'], $action['action']);
                }
                if ($action['backEnd'] === true) {
                    add_action('wp_ajax_' . $action['name'], $action['action']);
                }
            }
            foreach (static::$formActions as $action) {
                if ($action['frontEnd'] === true) {
                    add_action('admin_post_nopriv_' . $action['name'], $action['action']);
                }
                if ($action['backEnd'] === true) {
                    add_action('admin_post_' . $action['name'], $action['action']);
                }
            }
            //            echo '<pre>';
            //            var_dump(static::$formActions);
            //            var_dump($GLOBALS['wp_filter']);
            //            die('</pre>');
        });
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function addAction(string $name, callable $function, int $priority = null)
    {
        static::$actions[$name][] = ['function' => $function, 'priority' => $priority === null ? 10 : $priority];
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function removeAction(string $name, callable $function, int $priority = null)
    {
        if (array_key_exists($name, static::$actions)) {
            unset(static::$actions[$name]);
        }
        remove_action($name, $function, $priority === null ? 10 : $priority);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function addAjaxAction(string $name, callable $action, bool $backEnd = true, bool $frontEnd = false)
    {
        static::$ajaxActions[] = ['name' => $name, 'action' => $action, 'backEnd' => $backEnd, 'frontEnd' => $frontEnd];
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function addFormAction(string $name, callable $action, bool $backEnd = true, bool $frontEnd = false)
    {
        static::$formActions[] = ['name' => $name, 'action' => $action, 'backEnd' => $backEnd, 'frontEnd' => $frontEnd];
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasAction(string $name) : bool
    {
        return (bool) has_action($name, false);
    }
    
    /**
     * method
     * 
     * 
     * @return ?int
     */
    
    public static function getActionPriority(string $name, callable $action)
    {
        if (static::hasAction($name)) {
            return (int) has_action($name, $action);
        }
        return null;
    }

}