<?php
/**
 * Plugin Name: Travel App
 * Plugin URI: https://github.com/akirk/travel-app
 * Description: Turn booking confirmations into day-by-day travel itineraries you can follow, map, share and journal, all kept privately on your own site.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Alex Kirk
 * Author URI: https://alex.kirk.at/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: travel-app
 *
 * @package TravelApp
 */

namespace TravelApp;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRAVEL_APP_PLUGIN_FILE', __FILE__ );
define( 'TRAVEL_APP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRAVEL_APP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TRAVEL_APP_PLUGIN_DIR . 'vendor/autoload.php';

function is_playground(): bool {
    return defined( 'PLAYGROUND_AUTO_LOGIN_AS_USER' );
}

// Autoloader for plugin classes.
spl_autoload_register( function( $class ) {
    $prefix = 'TravelApp\\';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $file = TRAVEL_APP_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
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
