<?php

/*

    See license information at the package root in LICENSE.md

    Plugin Name: WP Dev/helper
    Version: 0.90.1 (main)
    Plugin URI: https://ion.digital/wp-devhelper
    Author: Justus Meyer
    Author URI: https://ion.digital
    Description: WP Devhelper provides a list of methods to aid in WordPress theme- and plug-in development, as well as various tools to aid in debugging.
    Text Domain: ion
    Domain Path: /languages

 */

$bootstrap = realpath(__DIR__ . "/vendor/ion/packaging/bootstrap.php") ?: realpath(__DIR__ . "/../packaging/bootstrap.php");

if(!empty($bootstrap))
    require_once($bootstrap);

\Ion\Package::create("ion", "wp-devhelper", function($package) {

    $loader = \Ion\Autoloading\Autoloader::create(
        
        $package, 
        [ 
            "source/classes",
            "source/interfaces",
            "source/traits"
        ], 
        [
            "builds/" . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION,
            "builds/" . PHP_MAJOR_VERSION,
        ]
    );

    if (defined("ABSPATH")) {

        require_once( ABSPATH . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'upgrade.php' );
        
        \Ion\WordPress\WordPressHelper::createContext('ion', 'wp-devhelper', __FILE__, __DIR__, [])
                
            ->initialize(function($context) {

                // empty for now!
            })
        
            ->activate(function($context) {
        
                // empty for now!
            })
            
            ->deactivate(function($context) {
        
                // empty for now!
            })
        
            ->uninstall(null)
        
        ->finalize();    
    }

    return $loader;
    
}, __FILE__);

