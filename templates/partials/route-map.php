<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Component variables are render-local state.
use TravelApp\App;
use TravelApp\GeocodeCache;
$trip_id = (int) $trip_data['id'];
$segments  = $trip_data['segments'] ?? [];
$route_locations = [];
$route_entries = [];

foreach ( $segments as $segment ) {
    // Public shares expose transport locations only, as on the timeline.
    if ( $is_shared_map && 'public' === $share_mode && ! in_array( (string) ( $segment['type'] ?? '' ), [ 'flight', 'train' ], true ) && 1 !== preg_match( '/\bbus(?:ses|es)?\b/i', (string) ( $segment['title'] ?? '' ) . ' ' . (string) ( $segment['details'] ?? '' ) ) ) {
        continue;
    }
    foreach ( [ 'location', 'end_location' ] as $location_key ) {
        $location = trim( (string) ( $segment[ $location_key ] ?? '' ) );

        if ( '' === $location ) {
            continue;
        }

        if ( empty( $route_locations ) || end( $route_locations ) !== $location ) {
            $route_locations[] = $location;
            $route_entries[] = [
                'id'       => (int) ( $segment['id'] ?? count( $route_entries ) ),
                'is_end'   => 'end_location' === $location_key,
                'location' => $location,
                'kind'     => 'end_location' === $location_key ? __( 'End location', 'travel-app' ) : __( 'Location', 'travel-app' ),
                'title'    => (string) ( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ),
                'type'     => (string) ( $segment['type'] ?? '' ),
                'date'     => (string) ( $segment['date'] ?? '' ),
                'time'     => (string) ( $segment['time'] ?? '' ),
                'details'  => ( $is_shared_map && 'public' === $share_mode ) ? '' : (string) ( $segment['details'] ?? '' ),
                'url'      => $back_url . '#segment-' . (int) ( $segment['id'] ?? 0 ),
            ];
        }
    }
}

$route_location_names = [];
foreach ( $route_entries as $route_entry ) {
    if ( ! in_array( $route_entry['location'], $route_location_names, true ) ) {
        $route_location_names[] = $route_entry['location'];
    }
}

// Coordinates this site already looked up, so a revisited trip draws at once.
$known_locations = GeocodeCache::get_many( $route_location_names );

// Drawn rather than typed: the play and pause characters are missing or turn into emoji
// depending on the font.
$playback_icons = [
    'play'  => '<svg class="playback-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2.6 1.4 10.2 6l-7.6 4.6Z" fill="currentColor"/></svg>',
    'pause' => '<svg class="playback-icon" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><rect x="2.4" y="1.5" width="2.7" height="9" fill="currentColor"/><rect x="6.9" y="1.5" width="2.7" height="9" fill="currentColor"/></svg>',
];

$map_strings = [
    'library'         => __( 'The map library could not be loaded.', 'travel-app' ),
    'too_few'         => __( 'Add at least two itinerary locations to draw a route.', 'travel-app' ),
    'untitled'        => __( 'Untitled item', 'travel-app' ),
    'cached'          => __( 'Cached coordinates', 'travel-app' ),
    'queued'          => __( 'Waiting for its turn', 'travel-app' ),
    'looking'         => __( 'Looking it up on OpenStreetMap...', 'travel-app' ),
    'found'           => __( 'Looked up on OpenStreetMap', 'travel-app' ),
    'missing'         => __( 'OpenStreetMap does not know this place', 'travel-app' ),
    'failed'          => __( 'The lookup failed', 'travel-app' ),
    'hidden'          => __( 'Hidden from the map', 'travel-app' ),
    'auto_pick'       => __( 'Best match for this itinerary', 'travel-app' ),
    /* translators: %s: number of the match in the list of possible places. */
    'pick_number'     => __( 'Match %s', 'travel-app' ),
    /* translators: 1: number of the location being looked up, 2: number of locations to look up. */
    'progress'        => __( 'Looking up location %1$s of %2$s on OpenStreetMap. It allows one lookup per second; results are then cached.', 'travel-app' ),
    /* translators: 1: number of waypoints shown, 2: number of waypoints in the itinerary. */
    'summary_points'  => __( '%1$s of %2$s waypoints on the map', 'travel-app' ),
    /* translators: %s: number of waypoints whose coordinates were already known. */
    'summary_cached'  => __( '%s from the cache', 'travel-app' ),
    /* translators: %s: number of waypoints looked up on OpenStreetMap just now. */
    'summary_looked'  => __( '%s looked up', 'travel-app' ),
    /* translators: %s: number of waypoints that could not be found. */
    'summary_missing' => __( '%s not found', 'travel-app' ),
    'summary_none'    => __( 'None of the itinerary locations could be placed on the map.', 'travel-app' ),
    'pause'           => __( 'Pause', 'travel-app' ),
    'resume'          => __( 'Play', 'travel-app' ),
    'replay'          => __( 'Play again', 'travel-app' ),
    'step_previous'   => __( 'Previous', 'travel-app' ),
    'step_current'    => __( 'This step', 'travel-app' ),
    'step_next'       => __( 'Next', 'travel-app' ),
    'route_start'     => __( 'Start of the route', 'travel-app' ),
    'route_end'       => __( 'End of the route', 'travel-app' ),
    /* translators: 1: number of the current step, 2: number of steps in the route. */
    'position'        => __( 'Step %1$s of %2$s', 'travel-app' ),
    /* translators: %s: distance in kilometres. */
    'leg'             => __( '%s km from the previous step', 'travel-app' ),
    /* translators: %s: distance in kilometres. */
    'leg_item'        => __( '%s km on this leg', 'travel-app' ),
];

// Leaflet ships with the plugin: wordpress.org does not allow loading assets
// from a CDN. Printed in the head so the route map script can use L.
$leaflet_base_url = TRAVEL_APP_PLUGIN_URL . 'assets/vendor/leaflet/';
wp_app_enqueue_style( 'travel-app-leaflet', $leaflet_base_url . 'leaflet.css', [], '1.9.4', $is_shared_map ? 'global' : 'travel-app' );
wp_app_enqueue_script( 'travel-app-leaflet', $leaflet_base_url . 'leaflet.js', [], '1.9.4', false, $is_shared_map ? 'global' : 'travel-app' );
$map_data = [
    'entries'  => array_values( $route_entries ),
    'seeded'   => (object) $known_locations,
    'i18n'     => $map_strings,
    'icons'    => $playback_icons,
    'demoMode' => $travel_app->is_demo_mode_enabled(),
    'ajax'     => $is_shared_map ? [] : [
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'travel_app_geocode' ),
    ],
];
$travel_app->enqueue_template_assets( 'map', true, '', [], $is_shared_map ? 'global' : '' );

?>
<div class="travel-route-map<?php echo $is_embedded_map ? ' travel-route-map-compact' : ''; ?>" data-route-map tabindex="-1">
    <template data-route-map-data><?php echo esc_html( wp_json_encode( $map_data ) ); ?></template>
        <section class="map-shell" aria-label="<?php esc_attr_e( 'Route map', 'travel-app' ); ?>">
            <button type="button" class="map-fullscreen-button" data-map-fullscreen aria-pressed="false" aria-label="<?php esc_attr_e( 'Expand map', 'travel-app' ); ?>" title="<?php esc_attr_e( 'Expand map', 'travel-app' ); ?>" data-expand-label="<?php esc_attr_e( 'Expand map', 'travel-app' ); ?>" data-collapse-label="<?php esc_attr_e( 'Restore map size', 'travel-app' ); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path class="map-expand-icon" d="M14 4h6v6m0-6-7 7M10 20H4v-6m0 6 7-7" />
                    <path class="map-collapse-icon" d="M20 4l-7 7m0-6v6h6M4 20l7-7m-6 0h6v6" />
                </svg>
            </button>
            <div class="map-consent" data-map-consent>
                <div>
                    <h2><?php esc_html_e( 'Load Route Map', 'travel-app' ); ?></h2>
                    <p><?php esc_html_e( 'The route map loads map tiles from OpenStreetMap and looks up itinerary locations with Nominatim.', 'travel-app' ); ?></p>
                </div>
                <button type="button" data-map-load><?php esc_html_e( 'Load Map', 'travel-app' ); ?></button>
            </div>
            <div class="route-map-canvas" data-map-canvas></div>
        </section>

        <div class="playback" data-playback hidden>
            <div class="playback-controls">
                <button class="playback-play" type="button" data-playback-start><?php echo $playback_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A hard-coded SVG literal defined at the top of this file. ?><?php esc_html_e( 'Playback', 'travel-app' ); ?></button>
                <span class="playback-transport" data-playback-transport hidden>
                    <button type="button" data-playback-step="-1" aria-label="<?php esc_attr_e( 'Previous waypoint', 'travel-app' ); ?>" title="<?php esc_attr_e( 'Previous waypoint', 'travel-app' ); ?>">&#x2190;</button>
                    <button class="playback-play" type="button" data-playback-toggle></button>
                    <button type="button" data-playback-step="1" aria-label="<?php esc_attr_e( 'Next waypoint', 'travel-app' ); ?>" title="<?php esc_attr_e( 'Next waypoint', 'travel-app' ); ?>">&#x2192;</button>
                    <span class="playback-position" data-playback-position></span>
                    <button type="button" data-playback-fit hidden title="<?php esc_attr_e( 'Fit each step to the map again', 'travel-app' ); ?>"><?php esc_html_e( 'Fit step', 'travel-app' ); ?></button>
                    <button type="button" data-playback-stop><?php esc_html_e( 'Exit', 'travel-app' ); ?></button>
                </span>
            </div>
            <div class="playback-steps" data-playback-steps hidden>
                <button class="playback-step playback-side" type="button" data-playback-card="previous" data-playback-step="-1"></button>
                <div class="playback-step playback-now" data-playback-card="current" aria-live="polite"></div>
                <button class="playback-step playback-side" type="button" data-playback-card="next" data-playback-step="1"></button>
            </div>
        </div>

        <p class="map-status" data-map-status><?php esc_html_e( 'Map services load only after you choose Load Map.', 'travel-app' ); ?></p>

        <?php if ( $route_entries ) : ?>
        <section class="route-list" aria-label="<?php esc_attr_e( 'Waypoints', 'travel-app' ); ?>">
            <div class="route-list-head">
                <h2><?php esc_html_e( 'Waypoints', 'travel-app' ); ?></h2>
                <div class="route-list-actions">
                    <button type="button" data-route-select="all"><?php esc_html_e( 'Show all', 'travel-app' ); ?></button>
                    <button type="button" data-route-select="none"><?php esc_html_e( 'Hide all', 'travel-app' ); ?></button>
                    <button type="button" data-route-refresh><?php esc_html_e( 'Look up again', 'travel-app' ); ?></button>
                </div>
            </div>

            <ol class="route-list-items">
                <?php foreach ( $route_entries as $route_index => $route_entry ) : ?>
                    <?php
                    $route_meta = array_filter( [
                        $route_entry['type'],
                        $route_entry['kind'],
                        trim( $route_entry['date'] . ' ' . $route_entry['time'] ),
                    ] );
                    $route_key = (string) $route_entry['id'];
                    ?>
                    <li class="route-item" data-route-item="<?php echo esc_attr( (string) $route_index ); ?>">
                        <label class="route-item-check">
                            <input type="checkbox" checked data-route-toggle="<?php echo esc_attr( (string) $route_index ); ?>">
                            <span class="route-item-number" data-route-number aria-hidden="true">-</span>
                        </label>
                        <div class="route-item-body">
                            <button type="button" class="route-item-focus" data-route-focus="<?php echo esc_attr( (string) $route_index ); ?>"><span<?php echo esc_attr( App::mask_attr( 'title', $route_key . '-item' ) ); ?>><?php echo esc_html( $route_entry['title'] ); ?></span></button>
                            <?php if ( $route_meta ) : ?>
                                <div class="route-item-meta"><?php echo esc_html( implode( ' · ', $route_meta ) ); ?></div>
                            <?php endif; ?>
                            <div class="route-item-meta"<?php echo esc_attr( App::mask_attr( 'place', $route_key . ( $route_entry['is_end'] ? '-end-location' : '-location' ) ) ); ?>><?php echo esc_html( $route_entry['location'] ); ?></div>
                            <div class="route-item-status" data-route-status></div>
                            <div class="route-item-status" data-route-match<?php echo esc_attr( App::mask_attr( 'place' ) ); ?>></div>
                            <label class="route-item-pick" hidden>
                                <span class="screen-reader-text"><?php esc_html_e( 'Matched place', 'travel-app' ); ?></span>
                                <select data-route-pick="<?php echo esc_attr( (string) $route_index ); ?>"></select>
                            </label>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>
</div>
