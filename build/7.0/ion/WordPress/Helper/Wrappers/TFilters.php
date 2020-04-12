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
use ion\Types\StringObject;
/**
 *
 * @author Justus
 */
trait TFilters
{
    private static $filters = [];
    /**
     * method
     * 
     * @return mixed
     */
    
    protected static function initialize_TFilters()
    {
        static::registerWrapperAction('init', function () {
            foreach (array_keys(static::$filters) as $key) {
                foreach (static::$filters[$key] as $filter) {
                    add_filter($key, $filter);
                }
            }
        });
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function addFilter(string $name, callable $function)
    {
        static::$filters[$name][] = $function;
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function removeFilter(string $name, callable $function, int $priority = null)
    {
        if (array_key_exists($name, static::$filters)) {
            unset(static::$filters[$name]);
        }
        remove_filter($name, $function, $priority === null ? 10 : $priority);
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function addContentFilter(callable $function)
    {
        static::addFilter('the_content', $function);
    }

}