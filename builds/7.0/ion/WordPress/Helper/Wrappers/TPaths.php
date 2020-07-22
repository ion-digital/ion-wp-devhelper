<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper\Wrappers;

use Throwable;
use WP_Post;
use ion\WordPress\IWordPressHelper;
use ion\WordPress\Helper\Tools;
use ion\WordPress\Helper\Constants;
use ion\PhpHelper as PHP;
use ion\Package;
use ion\ISemVer;
use ion\SemVer;
/**
 * Description of TPaths
 *
 * @author Justus
 */
trait TPaths
{
    private static $helperDir = null;
    private static $helperUri = null;
    /**
     * method
     * 
     * @return mixed
     */
    
    protected static function initialize_TPaths()
    {
        //        static::registerWrapperAction('init', function() {
        //
        //        });
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getHelperDirectory() : string
    {
        return static::$helperDir;
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getHelperUri() : string
    {
        return static::$helperUri;
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getWordPressPath() : string
    {
        return rtrim((string) ABSPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getWordPressUri() : string
    {
        return rtrim(get_site_url(), '/') . '/';
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getSitePath() : string
    {
        return rtrim(get_home_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getSiteUri() : string
    {
        return rtrim(get_home_url(), '/') . '/';
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getContentPath() : string
    {
        return rtrim(constant('WP_CONTENT_DIR'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    /**
     * method
     * 
     * @return string
     */
    
    public static function getContentUri() : string
    {
        return rtrim(content_url(), '/') . '/';
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function ensureTemporaryFilePath(string $filename, string $relativePath = null) : string
    {
        return static::ensureTemporaryFileDirectory($relativePath) . $filename;
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getThemePath(bool $includeChildTheme = true) : string
    {
        if ($includeChildTheme) {
            return get_stylesheet_directory() . DIRECTORY_SEPARATOR;
        }
        return get_template_directory() . DIRECTORY_SEPARATOR;
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getThemeUri(bool $includeChildTheme = true) : string
    {
        if ($includeChildTheme) {
            return get_stylesheet_directory_uri() . '/';
        }
        return get_template_directory_uri() . "/";
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getTemporaryFileDirectory(string $relativePath = null) : string
    {
        return get_temp_dir() . ($relativePath === null ? "" : trim($relativePath, "/ ")) . "/";
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getTemporaryFilePath(string $filename, string $relativePath = null) : string
    {
        return static::getTemporaryFileDirectory($relativePath) . $filename;
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
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
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getAdminUrl(string $filename, string $page = null) : string
    {
        $uri = admin_url($filename . (!PHP::strEndsWith($filename, '.php') ? '.php' : ''));
        if ($page !== null) {
            $uri .= "?page={$page}";
        }
        return esc_url($uri);
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
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
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getBackEndUri(string $path = null, int $blogId = null) : string
    {
        return get_admin_url($blogId, $path === null ? '' : $path, 'admin');
    }
    
    /**
     * method
     * 
     * 
     * @return string
     */
    
    public static function getPostUri(int $id = null) : string
    {
        if ($id === null) {
            global $post;
            $id = $post->ID;
        }
        return get_permalink($id);
    }

}