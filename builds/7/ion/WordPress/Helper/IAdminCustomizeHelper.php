<?php

/*
 * See license information at the package root in LICENSE.md
 */

namespace ion\WordPress\Helper;

/**
 *
 * @author Justus
 */
interface IAdminCustomizeHelper {


    function addTextSetting(
            
            string $label,
            string $key,            
            string $default = null,            
            bool $multiLine = false,
            int $priority = null,
            string $transport = 'refresh'
            
        ): self;
    
    function addCheckBoxSetting(
            
            string $label,
            string $key,            
            bool $default = null,            
            int $priority = null,
            string $transport = 'refresh'
            
        ): self;    
    
    function addDropDownSetting(
            
            string $label,
            string $key,            
            $default = null,            
            array $options = [],
            int $priority = null,
            string $transport = 'refresh'
            
        ): self;
    
    function addMediaSetting(
            
            string $label,
            string $key,            
            $default = null,
            int $priority = null,
            string $transport = 'refresh'
            
        ): self;
    
}
