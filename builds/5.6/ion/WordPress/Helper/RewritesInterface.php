<?php
namespace ion\WordPress\Helper;

/**
 * Description of RewriteApiTrait*
 * @author Justus
 */
interface RewritesInterface
{
    /**
     * method
     * 
     * 
     * @return void
     */
    static function addRewriteRule($pattern, $target, $top = false);
    /**
     * method
     * 
     * 
     * @return void
     */
    static function flushRewriteRules($hard = true);
}