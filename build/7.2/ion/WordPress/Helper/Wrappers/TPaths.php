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
 * Description of TPaths
 *
 * @author Justus
 */
trait TPaths
{
    private static $helperDir = null;
    private static $helperUri = null;
    protected static function initialize_TPaths()
    {
        //        static::registerWrapperAction('init', function() {
        //
        //        });
    }
    
    public static function getHelperDirectory() : string
    {
        return static::$helperDir;
    }
    
    public static function getHelperUri() : string
    {
        return static::$helperUri;
    }
    
    public static function getWordPressPath() : string
    {
        return rtrim((string) ABSPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    public static function getWordPressUri() : string
    {
        return rtrim(get_site_url(), '/') . '/';
    }
    
    public static function getSitePath() : string
    {
        return rtrim(get_home_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    public static function getSiteUri() : string
    {
        return rtrim(get_home_url(), '/') . '/';
    }
    
    public static function getContentPath() : string
    {
        return rtrim(constant('WP_CONTENT_DIR'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    public static function getContentUri() : string
    {
        return rtrim(content_url(), '/') . '/';
    }
    
    public static function ensureTemporaryFilePath(string $filename, string $relativePath = null) : string
    {
        return static::ensureTemporaryFileDirectory($relativePath) . $filename;
    }
    
    public static function getThemePath(bool $includeChildTheme = true) : string
    {
        if ($includeChildTheme) {
            return get_stylesheet_directory() . DIRECTORY_SEPARATOR;
        }
        return get_template_directory() . DIRECTORY_SEPARATOR;
    }
    
    public static function getThemeUri(bool $includeChildTheme = true) : string
    {
        if ($includeChildTheme) {
            return get_stylesheet_directory_uri() . '/';
        }
        return get_template_directory_uri() . "/";
    }
    
    public static function getTemporaryFileDirectory(string $relativePath = null) : string
    {
        return get_temp_dir() . ($relativePath === null ? "" : trim($relativePath, "/ ")) . "/";
    }
    
    public static function getTemporaryFilePath(string $filename, string $relativePath = null) : string
    {
        return static::getTemporaryFileDirectory($relativePath) . $filename;
    }
    
    public static function ensureTemporaryFileDirectory(string $relativePath = null) : string
    {
        $directory = static::GetTemporaryFileDirectory($relativePath);
        if (is_dir($directory) === false) {
            if (wp_mkdir_p($directory) === false) {
                throw new WordPressHelperException('Could not created temporary path.');
            }
        }
        return $directory;
    }
    
    public static function getAdminUrl(string $filename) : string
    {
        //(!PHP::strEndsWith($filename, '.php') ? '.php' : '')
        return esc_url(admin_url($filename . '.php'));
    }
    
    public static function getAjaxUrl(string $name = null, array $parameters = null) : string
    {
        $url = static::getAdminUrl('admin-ajax');
        if ($name === null) {
            return static::getUrl($url);
        }
        $tmp = [];
        $tmp['action'] = $name;
        if ($parameters !== null) {
            foreach ($parameters as $key => $value) {
                $tmp[$key] = $value;
            }
        }
        return static::getUrl($url, null, $tmp);
    }
    
    public static function getBackEndUri(string $path = null, int $blogId = null) : string
    {
        return get_admin_url($blogId, $path === null ? '' : $path, 'admin');
    }
    
    public static function getPostUri(int $id = null) : string
    {
        if ($id === null) {
            global $post;
            $id = $post->ID;
        }
        return get_permalink($id);
    }

}