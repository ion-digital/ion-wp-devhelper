<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper\Wrappers;

/**
 *
 * @author Justus
 */
use ion\Types\Arrays\IVector;
use ion\Types\Arrays\IMap;
use ion\WordPress\Helper\IWordPressTaxonomy;
use WP_Term;

interface ITaxonomies
{
    /**
     * method
     * 
     * 
     * @return IWordPressTaxonomy
     */
    
    static function addTaxonomy($slug, $pluralLabel, $singularLabel, IVector $postTypes = null, $description = null, $registerMetaBox = true, callable $metaBoxCallback = null, $hierarchical = null, $sort = null, IMap $labels = null, $public = null, $publiclyQueryable = null, $showUi = null, $showInNavMenus = null, $showInMenu = null, $showTagcloud = null, $showInQuickEdit = null, $showAdminColumn = null, IVector $capabilities = null, $rewrite = null, $rewriteSlug = null, $rewriteWithFront = null, $rewriteHierarchical = null, $rewriteEndPointMask = null, $enableQueryVar = null, $queryVar = null, $showInRest = null, $restBase = null, $restControllerClass = null, callable $updateCountCallback = null);
    
    /**
     * method
     * 
     * 
     * @return void
     */
    
    static function addPostTypesToTaxonomy($taxonomy, IVector $postTypes);
    
    /**
     * method
     * 
     * 
     * @return ?WP_Term
     */
    
    static function getTermParent($termId);
    
    /**
     * method
     * 
     * 
     * @return array
     */
    
    static function getTermParents($termId);
    
    /**
     * method
     * 
     * 
     * @return ?string
     */
    
    static function getTaxonomyFromTerm($termSlug);
    
    /**
     * method
     * 
     * 
     * @return array
     */
    
    static function getTerms(array $taxonomies, $hideEmpty = false);

}