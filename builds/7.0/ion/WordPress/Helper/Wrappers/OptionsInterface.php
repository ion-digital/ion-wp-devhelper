<?php
namespace ion\WordPress\Helper\Wrappers;

use ion\WordPress\Helper\Wrappers\OptionMetaType;
use ion\WordPress\Helper\AdminCustomizeHelperInterface;
/**
 * Description of RewriteApiTrait*
 * @author Justus
 */
interface OptionsInterface
{
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getSiteOption(string $name, $default = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setSiteOption(string $name, $value = null, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasSiteOption(string $name) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removeSiteOption(string $name) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getPostOption(string $name, int $metaId, $default = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setPostOption(string $name, int $metaId, $value = null, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasPostOption(string $name, int $metaId) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removePostOption(string $name, int $metaId, $value = null) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getTermOption(string $name, int $metaId, $default = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setTermOption(string $name, int $metaId, $value = null, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasTermOption(string $name, int $metaId) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removeTermOption(string $name, int $metaId, $value = null) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getUserOption(string $name, int $metaId, $default = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setUserOption(string $name, int $metaId, $value = null, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasUserOption(string $name, int $metaId) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removeUserOption(string $name, int $metaId, $value = null) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getCommentOption(string $name, int $metaId, $default = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setCommentOption(string $name, int $metaId, $value = null, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasCommentOption(string $name, int $metaId) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removeCommentOption(string $name, int $metaId, $value = null) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getCustomizationOption(string $name, $default = null);
    /**
     * method
     * 
     * 
     * @return void
     */
    static function setCustomizationOption(string $name, $value = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasCustomizationOption(string $name) : bool;
    /**
     * method
     * 
     * 
     * @return void
     */
    static function removeCustomizationOption(string $name);
    /**
     * method
     * 
     * 
     * @return AdminCustomizeHelperInterface
     */
    static function addCustomizationSection(string $title, string $slug = null, int $priority = null, string $textDomain = null) : AdminCustomizeHelperInterface;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getOption(string $key, $default = null, int $id = null, OptionMetaType $type = null, bool $raw = false);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setOption(string $key, $value = null, int $id = null, OptionMetaType $type = null, bool $raw = false, bool $autoLoad = false) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function hasOption(string $key, int $id = null, OptionMetaType $type = null) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function removeOption(string $key, int $id = null, OptionMetaType $type = null) : bool;
    /**
     * method
     * 
     * 
     * @return mixed
     */
    static function getRawOption(string $key, $default = null, int $id = null, OptionMetaType $type = null);
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function setRawOption(string $key, $value = null, int $id = null, OptionMetaType $type = null, bool $autoLoad = false) : bool;
}