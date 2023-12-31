<?php
namespace ion\WordPress\Helper;

use WP_Term;
/**
 * Description of TaxonomiesTrait*
 * @author Justus
 */
interface TaxonomiesInterface
{
    /**
     * method
     * 
     * 
     * @return void
     */
    static function addTaxonomy(string $slug, string $pluralLabel, string $singularLabel, array $postTypes = null, string $description = null, bool $registerMetaBox = true, callable $metaBoxCallback = null, bool $hierarchical = null, bool $sort = null, array $labels = null, bool $public = null, bool $publiclyQueryable = null, bool $showUi = null, bool $showInNavMenus = null, bool $showInMenu = null, bool $showTagcloud = null, bool $showInQuickEdit = null, bool $showAdminColumn = null, array $capabilities = null, bool $rewrite = null, string $rewriteSlug = null, bool $rewriteWithFront = null, bool $rewriteHierarchical = null, string $rewriteEndPointMask = null, bool $enableQueryVar = null, string $queryVar = null, bool $showInRest = null, string $restBase = null, string $restControllerClass = null, callable $updateCountCallback = null) : void;
    /**
     * method
     * 
     * 
     * @return void
     */
    static function addPostTypesToTaxonomy(string $taxonomy, array $postTypes) : void;
    /**
     * method
     * 
     * 
     * @return ?string
     */
    static function getTaxonomyFromTerm(string $termSlug) : ?string;
    /**
     * method
     * 
     * 
     * @return ?WP_Term
     */
    static function getTermParent(int $termId) : ?\WP_Term;
    /**
     * method
     * 
     * 
     * @return array
     */
    static function getTermParents(int $termId) : array;
    /**
     * method
     * 
     * 
     * @return array
     */
    static function getTerms(array $taxonomies, bool $hierarchy = true, int $parent = null, bool $hideEmpty = false) : array;
}