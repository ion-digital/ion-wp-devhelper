<?php
/*
 * See license information at the package root in LICENSE.md
 */
namespace ion\WordPress\Helper\Wrappers;

/**
 *
 * @author Justus
 */

interface IActions
{
    static function addAction(string $name, callable $function, int $priority = null) : void;
    
    static function removeAction(string $name, callable $function, int $priority = null);
    
    static function addAjaxAction(string $name, callable $action, bool $backEnd = true, bool $frontEnd = false);
    
    static function hasAction(string $name) : bool;
    
    static function getActionPriority(string $name, callable $action) : ?int;

}