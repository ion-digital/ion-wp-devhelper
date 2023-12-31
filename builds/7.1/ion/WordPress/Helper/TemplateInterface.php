<?php
namespace ion\WordPress\Helper;

use ion\WordPress\Helper\WordPressWidgetInterface;
use WP_Query;
/**
 * Description of RewriteApiTrait*
 * @author Justus
 */
interface TemplateInterface
{
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function isFrontPage(int $postId = null) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function isPostsPage(int $postId = null) : bool;
    /**
     * method
     * 
     * @return bool
     */
    static function isBlogPage() : bool;
    /**
     * method
     * 
     * @return ?object
     */
    static function getUriObject();
    /**
     * method
     * 
     * @return ?int
     */
    static function getUriPostId() : ?int;
    /**
     * method
     * 
     * @return bool
     */
    static function isPage() : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function isPost(string $name = null) : bool;
    /**
     * method
     * 
     * 
     * @return bool
     */
    static function isCategory(string $name = null) : bool;
    /**
     * method
     * 
     * @return bool
     */
    static function isArchive() : bool;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function theLoop(callable $generator = null, int $limit = null, string $emptyText = null, bool $echo = false) : string;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function title(bool $echo = true) : string;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function content(bool $echo = true) : string;
    /**
     * method
     * 
     * 
     * @return void
     */
    static function addMenu(string $id, string $description = null) : void;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function menu(string $id, string $template = null, string $menuId = null, int $depth = 0, bool $echo = false) : string;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function siteLink(array $controllers = null, array $parameters = null, bool $absolute = true, bool $echo = true) : string;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function widget(WordPressWidgetInterface $widget, array $values = null, string $beforeWidget = null, string $afterWidget = null, string $beforeTitle = null, string $afterTitle = null, bool $echo = true) : string;
    /**
     * method
     * 
     * 
     * @return string
     */
    static function sideBar(string $id, bool $echo = true) : string;
    /**
     * method
     * 
     * @return bool
     */
    static function isPaginated() : bool;
    /**
     * method
     * 
     * @return int
     */
    static function getCurrentPage() : int;
    /**
     * method
     * 
     * 
     * @return array
     */
    static function getPageLinks(bool $prevNext = false, string $prevText = null, string $nextText = null) : array;
    /**
     * method
     * 
     * @return array
     */
    static function getSearchTerms() : array;
    /**
     * method
     * 
     * @return int
     */
    static function getPostsPerPage() : int;
    /**
     * method
     * 
     * 
     * @return int
     */
    static function getTotalPostCount(\WP_Query $wpQuery = null) : int;
    /**
     * method
     * 
     * 
     * @return ?object
     */
    static function getCurrentTemplateObject(bool $subject = false);
    /**
     * method
     * 
     * 
     * @return ?string
     */
    static function getCurrentTemplateObjectType(bool $subject = false) : ?string;
    /**
     * method
     * 
     * 
     * @return ?int
     */
    static function getCurrentTemplateObjectId(bool $subject = false) : ?int;
}