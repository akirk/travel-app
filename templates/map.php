<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
use TravelApp\App;
use TravelApp\Trip;

$travel_app = App::get_instance();
$trip_id = isset( $travel_app_template_context['trip_id'] ) ? absint( $travel_app_template_context['trip_id'] ) : absint( $travel_app->get_route_param( 'id' ) );
$is_shared_map = ! empty( $travel_app_template_context['is_shared_map'] );
$is_embedded_map = $is_shared_map && ! empty( $travel_app_template_context['is_embedded_map'] );
$share_mode = $is_shared_map ? (string) $travel_app_template_context['share_mode'] : '';
$back_url = $is_shared_map ? $travel_app->get_trip_share_url( $trip_id, $share_mode ) : home_url( '/travel-app/trip/' . $trip_id . '/' );
if ( $is_shared_map ) {
    show_admin_bar( false );
    remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
}
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'travel-app' ),
        esc_html__( 'Travel plan not found', 'travel-app' ),
        [ 'response' => 404 ]
    );
}

$trip_data = $trip->with_segments_user_id( $is_shared_map ? Trip::get_owner_id( $trip_id ) : null )->to_array();
$route_map_html = $travel_app->get_route_map_html( $trip_data, $is_shared_map, $share_mode, $back_url, $is_embedded_map );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
    <?php
    /* translators: %s: travel plan title. */
    wp_app_the_title( sprintf( __( '%s Route Map', 'travel-app' ), $trip_data['title'] ) ); 
    ?>
    </title>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <?php wp_app_head(); ?>
</head>
<body class="route-map-page">
    <?php wp_app_body_open(); ?>

    <main>
        <header class="map-header">
            <?php if ( ! $trip_data ) : ?>
                <h1><?php esc_html_e( 'Travel plan not found', 'travel-app' ); ?></h1>
                <p class="meta">
                    <span><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'travel-app' ); ?></span>
                    <a class="back-link" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back to Travel Plan', 'travel-app' ); ?></a>
                </p>
            <?php else : ?>
                <h1>
                    <?php
                    printf(
                        /* translators: %s: travel plan title. */
                        esc_html__( '%s Route Map', 'travel-app' ),
                        '<span' . esc_attr( App::mask_attr( 'title', (string) $trip_data['id'] ) ) . '>' . esc_html( $trip_data['title'] ) . '</span>'
                    );
                    ?>
                </h1>
                <p class="meta">
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of waypoints on the route. */
                                _n( '%d itinerary item', '%d itinerary items', count( $trip_data['segments'] ?? [] ), 'travel-app' ),
                                count( $trip_data['segments'] ?? [] )
                            )
                        );
                        ?>
                    </span>
                    <span><?php esc_html_e( 'Straight lines between itinerary locations', 'travel-app' ); ?></span>
                    <a class="back-link" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back to Travel Plan', 'travel-app' ); ?></a>
                </p>
            <?php endif; ?>
        </header>

        <?php echo $route_map_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped component HTML. ?>
    </main>

    <?php wp_app_body_close(); ?>
</body>
</html>
