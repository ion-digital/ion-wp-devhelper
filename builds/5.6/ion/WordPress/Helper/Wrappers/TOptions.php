<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper\Wrappers;

use \Exception as Throwable;
use WP_Post;
use ion\WordPress\IWordPressHelper;
use ion\WordPress\Helper\Tools;
use ion\WordPress\Helper\Constants;
use ion\Logging\LogLevel;
use ion\PhpHelper as PHP;
use ion\Package;
use ion\ISemVer;
use ion\SemVer;
use ion\WordPress\Helper\Wrappers\OptionMetaType;
use WP_Customize_Manager;
use WP_Customize_Media_Control;
use ion\WordPress\Helper\IAdminCustomizeHelper;
use ion\WordPress\Helper\AdminCustomizeHelper;
/**
 * Description of TRewriteApi
 *
 * @author Justus
 */
trait TOptions
{
    private static $themeOptions = [];
    /**
     * method
     * 
     * @return mixed
     */
    
    protected static function initialize_TOptions()
    {
        static::registerWrapperAction('customize_register', function (WP_Customize_Manager $wpCustomize) {
            //            echo("<pre>");
            //        var_dump(static::$themeOptions);
            //            die("</pre>");
            foreach (static::$themeOptions as $sectionSlug => $themeOption) {
                $wpCustomize->add_section($sectionSlug, ['title' => $themeOption['title'], 'priority' => $themeOption['priority']]);
                foreach ($themeOption['settings'] as $settingSlug => $setting) {
                    $wpCustomize->add_setting($settingSlug, ['default' => null, 'transport' => 'refresh']);
                    $label = !PHP::isEmpty($themeOption['textDomain']) ? __($setting['label'], $themeOption['textDomain']) : $setting['label'];
                    if ($setting['type'] == 'media') {
                        $wpCustomize->add_control(new WP_Customize_Media_Control($wpCustomize, $settingSlug, ['label' => $label, 'section' => $sectionSlug, 'settings' => $settingSlug, 'priority' => 8]));
                        continue;
                    }
                    if ($setting['type'] == 'select') {
                        $wpCustomize->add_control($settingSlug, ['label' => $label, 'section' => $sectionSlug, 'settings' => $settingSlug, 'type' => 'select', 'choices' => $setting['options']]);
                        continue;
                    }
                    if ($setting['type'] == 'checkbox') {
                        $wpCustomize->add_control($settingSlug, ['label' => $label, 'section' => $sectionSlug, 'settings' => $settingSlug, 'type' => 'checkbox']);
                        continue;
                    }
                    if ($setting['type'] == 'text') {
                        if ($setting['multiLine'] === true) {
                            $wpCustomize->add_control($settingSlug, ['label' => $label, 'section' => $sectionSlug, 'settings' => $settingSlug, 'type' => 'textarea']);
                            continue;
                        }
                        $wpCustomize->add_control($settingSlug, ['label' => $label, 'section' => $sectionSlug, 'settings' => $settingSlug, 'type' => 'text']);
                        continue;
                    }
                }
            }
        });
    }
    
    // --- DEPRECATED ---
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getOption($key, $default = null, $id = null, OptionMetaType $type = null, $raw = false)
    {
        if (static::hasOption($key, $id, $type) === false) {
            return $default;
        }
        $value = null;
        //        var_dump($type);
        if ($id === null) {
            $value = get_option($key, null);
        } else {
            if ($type === null) {
                $type = OptionMetaType::POST();
            }
            //            var_dump($type->toValue());
            switch ($type->toValue()) {
                case OptionMetaType::TERM:
                    $value = get_term_meta($id, $key, true);
                    //                    echo "<pre>";
                    //                    var_dump($id);
                    //                    var_dump($key);
                    //                    var_dump($value);
                    //                    echo "</pre>";
                    break;
                case OptionMetaType::USER:
                    $value = get_user_meta($id, $key, true);
                    break;
                case OptionMetaType::COMMENT:
                    $value = get_comment_meta($id, $key, true);
                    break;
                case OptionMetaType::POST:
                    $value = get_post_meta($id, $key, true);
                    break;
            }
        }
        if ($value === null || $value !== null && $value === '') {
            return $default;
        }
        if ($raw === true) {
            return $value;
        }
        $tmp = @unserialize($value);
        if ($tmp !== false) {
            return $tmp;
        }
        return false;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setOption($key, $value = null, $id = null, OptionMetaType $type = null, $raw = false, $autoLoad = false)
    {
        if ($raw === false) {
            $value = @serialize($value);
        } else {
            $value = $value === null ? '' : $value;
        }
        //($id === null ? update_option($key, $value, $autoLoad) : ($term ?  : ));
        if ($id === null) {
            return update_option($key, $value, $autoLoad);
        } else {
            if ($type === null) {
                $type = OptionMetaType::POST();
            }
            switch ($type->toValue()) {
                case OptionMetaType::TERM:
                    return update_term_meta($id, $key, $value);
                case OptionMetaType::USER:
                    return update_user_meta($id, $key, $value);
                case OptionMetaType::COMMENT:
                    return update_comment_meta($id, $key, $value);
                case OptionMetaType::POST:
                    return update_post_meta($id, $key, $value);
            }
        }
        return false;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasOption($key, $id = null, OptionMetaType $type = null)
    {
        $tmp = null;
        if ($type === null) {
            $type = OptionMetaType::POST();
        }
        switch ($type->toValue()) {
            case OptionMetaType::TERM:
                $tmp = 'term';
                break;
            case OptionMetaType::USER:
                $tmp = 'user';
                break;
            case OptionMetaType::COMMENT:
                $tmp = 'comment';
                break;
            case OptionMetaType::POST:
                $tmp = 'post';
                break;
        }
        return static::_hasOption($key, $tmp, $id);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removeOption($key, $id = null, OptionMetaType $type = null)
    {
        if ($id === null) {
            return delete_option($key);
        }
        if ($type === null) {
            $type = OptionMetaType::POST();
        }
        switch ($type->toValue()) {
            case OptionMetaType::TERM:
                return delete_term_meta($id, $key);
            case OptionMetaType::USER:
                return delete_user_meta($id, $key);
            case OptionMetaType::POST:
                return delete_post_meta($id, $key);
            case OptionMetaType::COMMENT:
                return delete_comment_meta($id, $key);
        }
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getRawOption($key, $default = null, $id = null, OptionMetaType $type = null)
    {
        return static::getOption($key, $default, $id, $type, true);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setRawOption($key, $value = null, $id = null, OptionMetaType $type = null, $autoLoad = false)
    {
        return static::setOption($key, $value, $id, $type, true, $autoLoad);
    }
    
    // --- USE THESE INSTEAD ---
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    private static function _hasOption($name, $type = null, $id = null)
    {
        global $wpdb;
        $sqlQuery = null;
        $optionField = 'option_name';
        if ($id === null || $type === null) {
            $sqlQuery = "SELECT * FROM `{$wpdb->prefix}options` WHERE {$optionField} LIKE ('{$name}') LIMIT 1";
        } else {
            $sqlQuery = "SELECT * FROM `{$wpdb->prefix}{$type}meta` WHERE `meta_key` LIKE ('{$name}') AND `{$type}_id` = {$id} LIMIT 1";
        }
        $results = $wpdb->get_results($sqlQuery, 'OBJECT');
        if (PHP::count($results) > 0) {
            return true;
        }
        return false;
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getSiteOption($name, $default = null)
    {
        if (!static::hasSiteOption($name)) {
            return $default;
        }
        $value = get_option($name, null);
        if (PHP::isEmpty($value, false, false)) {
            return $default;
        }
        return $value;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setSiteOption($name, $value = null, $autoLoad = false)
    {
        return (bool) update_option($name, $value, $autoLoad);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasSiteOption($name)
    {
        return static::_hasOption($name);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removeSiteOption($name)
    {
        return (bool) delete_option($name);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getPostOption($name, $metaId, $default = null)
    {
        if (!static::hasPostOption($name)) {
            return $default;
        }
        $value = get_post_meta($id, $name, true);
        if (PHP::isEmpty($value, false, false)) {
            return $default;
        }
        return $value;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setPostOption($name, $metaId, $value = null, $autoLoad = false)
    {
        return (bool) update_post_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasPostOption($name, $metaId)
    {
        return static::_hasOption($name, 'post', $id);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removePostOption($name, $metaId, $value = null)
    {
        return (bool) delete_post_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getTermOption($name, $metaId, $default = null)
    {
        if (!static::hasTermOption($name)) {
            return $default;
        }
        $value = get_term_meta($id, $name, true);
        if (PHP::isEmpty($value, false, false)) {
            return $default;
        }
        return $value;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setTermOption($name, $metaId, $value = null, $autoLoad = false)
    {
        return (bool) update_term_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasTermOption($name, $metaId)
    {
        return static::_hasOption($name, 'term', $id);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removeTermOption($name, $metaId, $value = null)
    {
        return (bool) delete_term_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getUserOption($name, $metaId, $default = null)
    {
        if (!static::hasUserOption($name)) {
            return $default;
        }
        $value = get_user_meta($id, $name, true);
        if (PHP::isEmpty($value, false, false)) {
            return $default;
        }
        return $value;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setUserOption($name, $metaId, $value = null, $autoLoad = false)
    {
        return (bool) update_user_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasUserOption($name, $metaId)
    {
        return static::_hasOption($name, 'user', $id);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removeUserOption($name, $metaId, $value = null)
    {
        return (bool) delete_user_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getCommentOption($name, $metaId, $default = null)
    {
        if (!static::hasCommentOption($name)) {
            return $default;
        }
        $value = get_comment_meta($id, $name, true);
        if (PHP::isEmpty($value, false, false)) {
            return $default;
        }
        return $value;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function setCommentOption($name, $metaId, $value = null, $autoLoad = false)
    {
        return (bool) update_comment_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasCommentOption($name, $metaId)
    {
        return static::_hasOption($name, 'comment', $id);
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function removeCommentOption($name, $metaId, $value = null)
    {
        return (bool) delete_comment_meta($metaId, $name, $value);
    }
    
    /**
     * method
     * 
     * 
     * @return mixed
     */
    
    public static function getCustomizationOption($name, $default = null)
    {
        if (!static::hasCustomizationOption($name)) {
            return $default;
        }
        return get_theme_mod($name, null);
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function setCustomizationOption($name, $value = null)
    {
        set_theme_mod($name, $value);
        return;
    }
    
    /**
     * method
     * 
     * 
     * @return bool
     */
    
    public static function hasCustomizationOption($name)
    {
        return static::getCustomizationOption($name) !== null;
    }
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    public static function removeCustomizationOption($name)
    {
        remove_theme_mod($name);
        return;
    }
    
    /**
     * method
     * 
     * 
     * @return IAdminCustomizeHelper
     */
    
    public static function addCustomizationSection($title, $slug = null, $priority = null, $textDomain = null)
    {
        $themeOption = ['slug' => $slug === null ? WP::slugify($title) : $slug, 'title' => $title, 'priority' => $priority === null ? 30 : $priority, 'textDomain' => $textDomain, 'settings' => []];
        static::$themeOptions[$themeOption['slug']] =& $themeOption;
        return new AdminCustomizeHelper($themeOption['settings']);
    }

}