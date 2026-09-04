<?php
/**
 * Plugin Name: Traveler
 * Plugin URI: https://github.com/akirk/traveler
 * Description: Turn booking confirmations into day-by-day travel itineraries you can follow, map, share and journal, all kept privately on your own site.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: traveler
 *
 * @package Traveler
 */

namespace Traveler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

function is_playground(): bool {
    return defined( 'PLAYGROUND_AUTO_LOGIN_AS_USER' );
}

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'Traveler\\';
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
