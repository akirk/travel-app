<?php
/**
 * Plugin Name: Travel App
 * Description: A private travel organizer for WordPress.
 * Version: 1.0.0
 * Author: Alex Kirk
 * Text Domain: travel-app
 * Requires PHP: 7.4
 */

namespace TravelApp;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'TravelApp\\';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

add_action( 'plugins_loaded', function() {
    App::get_instance()->init();
} );

register_activation_hook( __FILE__, function() {
    App::get_instance()->activate();
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );
