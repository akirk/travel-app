<?php
use TravelApp\App;
use TravelApp\LodgingCoverage;
use TravelApp\Trip;

global $wp_app_route;

$travel_app = App::get_instance();
$demo_mode_enabled = $travel_app->is_demo_mode_enabled();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$share_token = isset( $wp_app_route['params']['token'] ) ? sanitize_text_field( wp_unslash( $wp_app_route['params']['token'] ) ) : '';
$is_static_download = ! empty( $travel_app_static_download );
$is_shared_timeline = ! empty( $travel_app_shared_timeline ) || '' !== $share_token;
$is_readonly_timeline = $is_shared_timeline || $is_static_download;
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'travel-app' ),
        esc_html__( 'Travel plan not found', 'travel-app' ),
        [ 'response' => 404 ]
    );
}
$share_mode = $is_static_download ? ( isset( $travel_app_static_share_mode ) ? (string) $travel_app_static_share_mode : 'fellow' ) : ( $is_shared_timeline ? $travel_app->get_trip_share_mode_by_token( $trip_id, $share_token ) : '' );
$show_private_share_details = ( ! $is_shared_timeline && ! $is_static_download ) || 'fellow' === $share_mode;
$updated    = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : null;
$trip_updated = isset( $_GET['trip_updated'] ) ? absint( $_GET['trip_updated'] ) : null;
$error      = isset( $_GET['travel_app_error'] ) ? sanitize_key( wp_unslash( $_GET['travel_app_error'] ) ) : '';
$quick_plan_draft_key = isset( $_GET['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_GET['quick_plan_draft'] ) ) : '';
$quick_plan_draft = '' !== $quick_plan_draft_key ? $travel_app->get_quick_plan_draft( $quick_plan_draft_key ) : [];
$quick_plan_draft_target = isset( $quick_plan_draft['target_trip_id'] ) ? absint( $quick_plan_draft['target_trip_id'] ) : 0;
$quick_plan_segment = $quick_plan_draft_target === $trip_id && isset( $quick_plan_draft['segment'] ) && is_array( $quick_plan_draft['segment'] )
    ? $quick_plan_draft['segment']
    : [];

$segments_user_id = null;
if ( $is_shared_timeline ) {
    $segments_user_id = Trip::get_owner_id( $trip_id );
}
$trip_data = $trip->with_segments_user_id( $segments_user_id )->to_array();
$segments  = $trip_data['segments'] ?? [];
$is_trip_active = $travel_app->is_trip_active( $trip_data );
$fellow_share_url = ! $is_shared_timeline ? $travel_app->get_trip_share_url( (int) $trip_data['id'], 'fellow' ) : '';
$public_share_url = ! $is_shared_timeline ? $travel_app->get_trip_share_url( (int) $trip_data['id'], 'public' ) : '';
$segment_type_labels = [
    'flight'   => __( 'Flight', 'travel-app' ),
    'lodging'  => __( 'Lodging', 'travel-app' ),
    'train'    => __( 'Train', 'travel-app' ),
    'car'      => __( 'Rental car', 'travel-app' ),
    'activity' => __( 'Activity', 'travel-app' ),
    'other'    => __( 'Other', 'travel-app' ),
];
$lodging_coverage = LodgingCoverage::analyze( $trip_data, $segments );
$timeline_segments = LodgingCoverage::timeline_segments( $segments );
$lodging_required_nights = $lodging_coverage['required_nights'];
$covered_lodging_night_details = $lodging_coverage['covered_details'];
$missing_lodging_nights = $lodging_coverage['missing_nights'];
$lodging_missing_ranges = $lodging_coverage['missing_ranges'];
$missing_lodging_night_details = $lodging_coverage['missing_details'];

foreach ( $timeline_segments as &$timeline_segment ) {
    if ( 'checkout' === ( $timeline_segment['_timeline_kind'] ?? '' ) && '' === (string) ( $timeline_segment['title'] ?? '' ) ) {
        $timeline_segment['title'] = __( 'Lodging', 'travel-app' );
    }

    if ( 'return' === ( $timeline_segment['_timeline_kind'] ?? '' ) && '' === (string) ( $timeline_segment['title'] ?? '' ) ) {
        $timeline_segment['title'] = __( 'Rental car', 'travel-app' );
    }
}
unset( $timeline_segment );

$segments_by_day = [];
foreach ( $timeline_segments as $segment ) {
    $day = ! empty( $segment['date'] ) ? (string) $segment['date'] : 'unscheduled';
    $segments_by_day[ $day ][] = $segment;
}

$unscheduled_segments = $segments_by_day['unscheduled'] ?? [];
unset( $segments_by_day['unscheduled'] );

$today = current_time( 'Y-m-d' );
if ( $is_trip_active && '' !== $today && ! isset( $segments_by_day[ $today ] ) ) {
    $segments_by_day[ $today ] = [];
    ksort( $segments_by_day );
}

$demo_start = $trip_data['starts_at'] ?? '';
if ( '' === $demo_start ) {
    $demo_start = gmdate( 'Y-m-d' );
}
$demo_start_time = $demo_start . 'T12:00';

$get_google_maps_url = static function( string $address ): string {
    $address = trim( $address );

    if ( '' === $address ) {
        return '';
    }

    return add_query_arg(
        [
            'api'   => '1',
            'query' => $address,
        ],
        'https://www.google.com/maps/search/'
    );
};

$get_google_maps_route_url = static function( array $locations ): string {
    $locations = array_values( array_filter( array_map( 'trim', $locations ) ) );

    if ( count( $locations ) < 2 ) {
        return '';
    }

    $origin = array_shift( $locations );
    $destination = array_pop( $locations );
    $args = [
        'api'         => '1',
        'origin'      => $origin,
        'destination' => $destination,
        'travelmode'  => 'driving',
    ];

    if ( ! empty( $locations ) ) {
        $args['waypoints'] = implode( '|', $locations );
    }

    return add_query_arg( $args, 'https://www.google.com/maps/dir/' );
};

$is_transport_segment = static function( array $segment ): bool {
    $type = (string) ( $segment['type'] ?? '' );
    if ( in_array( $type, [ 'flight', 'train' ], true ) ) {
        return true;
    }

    return 1 === preg_match( '/\bbus(?:ses|es)?\b/i', (string) ( $segment['title'] ?? '' ) . ' ' . (string) ( $segment['details'] ?? '' ) );
};

$route_locations = [];
foreach ( $segments as $segment ) {
    foreach ( [ 'location', 'end_location' ] as $location_key ) {
        $location = trim( (string) ( $segment[ $location_key ] ?? '' ) );

        if ( '' === $location ) {
            continue;
        }

        if ( empty( $route_locations ) || end( $route_locations ) !== $location ) {
            $route_locations[] = $location;
        }
    }
}

$trip_route_links = [];
$trip_direct_map_url = '';
if ( count( $route_locations ) >= 2 ) {
    $trip_route_links['google'] = $get_google_maps_route_url( $route_locations );
    $trip_direct_map_url = $is_static_download ? '' : home_url( '/travel-app/trip/' . (int) $trip_data['id'] . '/map/' );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( $trip_data ? $trip_data['title'] : __( 'Travel Plan', 'travel-app' ) ); ?></title>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_head(); ?>
    <?php endif; ?>
    <style>
        :root {
            color-scheme: light dark;
            <?php if ( $is_static_download ) : ?>
                --wp-app-color-background: #f8fafc;
                --wp-app-color-surface: #fff;
                --wp-app-color-text: #17202a;
                --wp-app-color-muted: #5f6b7a;
                --wp-app-color-border: #d8dee8;
                --wp-app-color-link: #0b6bcb;
            <?php endif; ?>
        }
        <?php if ( $is_static_download ) : ?>
            @media (prefers-color-scheme: dark) {
                :root {
                    --wp-app-color-background: #111418;
                    --wp-app-color-surface: #191e24;
                    --wp-app-color-text: #f1f5f9;
                    --wp-app-color-muted: #a7b0bd;
                    --wp-app-color-border: #303844;
                    --wp-app-color-link: #7ab7ff;
                }
            }
        <?php endif; ?>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.5;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
        }
        main { max-width: 980px; margin: 0 auto; padding: 32px 18px 56px; }
        a { color: var(--wp-app-color-link); }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2rem, 5vw, 3.5rem); line-height: 1.04; margin-bottom: 12px; letter-spacing: 0; }
        h2 { font-size: 1.15rem; margin-bottom: 14px; }
        h3 { font-size: 1rem; margin-bottom: 5px; }
        .screen-reader-text {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            word-wrap: normal;
            border: 0;
        }
        label { display: block; font-weight: 650; margin-bottom: 5px; }
        input, select, textarea {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            padding: 9px 10px;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
            font: inherit;
        }
        textarea { min-height: 92px; resize: vertical; }
        button {
            appearance: none;
            min-height: 38px;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid transparent;
            background: var(--wp-app-color-link);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .topbar { margin-bottom: 24px; }
        .trip-title-header {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        .trip-title-header h1 {
            margin: 0;
            overflow-wrap: anywhere;
        }
        .trip-title-edit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-height: 38px;
            padding: 0;
            border-radius: 6px;
            border: 1px solid var(--wp-app-color-border);
            background: transparent;
            color: var(--wp-app-color-link);
        }
        .trip-title-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }
        .trip-title-form[hidden] { display: none; }
        .trip-title-form label { margin: 0; }
        .trip-title-form input { font-size: 1.35rem; font-weight: 750; }
        .meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; color: var(--wp-app-color-muted); margin-bottom: 24px; }
        .trip-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .trip-actions .ghost-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 38px;
            box-sizing: border-box;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
        }
        .share-link {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }
        .share-option {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
            padding: 10px 0;
            border-top: 1px solid var(--wp-app-color-border);
        }
        .share-option:first-child { border-top: 0; padding-top: 0; }
        .share-link label { margin: 0; }
        .share-link input { color: var(--wp-app-color-muted); }
        .share-link .ghost-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            box-sizing: border-box;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
        }
        .share-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .share-actions .copied {
            border-color: rgba(15, 107, 66, 0.42);
            color: #0f6b42;
        }
        .panel {
            background: var(--wp-app-color-surface);
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 18px;
        }
        .notice {
            margin-bottom: 18px;
            border-radius: 6px;
            padding: 12px 14px;
            border: 1px solid rgba(15, 107, 66, 0.32);
            background: rgba(15, 107, 66, 0.08);
        }
        .notice.error { border-color: rgba(138, 75, 8, 0.28); background: rgba(138, 75, 8, 0.08); }
        .demo-controls { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 18px; }
        .demo-controls label { min-width: 190px; margin: 0; }
        .ghost-button {
            background: transparent;
            color: var(--wp-app-color-text);
            border-color: var(--wp-app-color-border);
        }
        .timeline { position: relative; display: grid; gap: 0; }
        .timeline-day { position: relative; padding-left: 26px; border-left: 2px solid var(--wp-app-color-border); }
        .timeline-day.empty { min-height: 96px; }
        .timeline-day.current { border-left-color: var(--wp-app-color-link); }
        .timeline-day.past { opacity: 0.62; }
        .time-marker {
            display: none;
            position: absolute;
            z-index: 2;
            left: 26px;
            right: 0;
            height: 0;
            border-top: 2px solid #c62828;
            pointer-events: none;
        }
        .time-marker span {
            position: absolute;
            right: 0;
            top: -15px;
            padding: 2px 6px;
            border-radius: 999px;
            background: #c62828;
            color: #fff;
            font-size: 0.76rem;
            font-weight: 750;
        }
        .day-heading {
            position: relative;
            margin: 0 0 10px;
            padding-top: 2px;
            color: var(--wp-app-color-muted);
            font-size: 0.92rem;
            font-weight: 750;
        }
        .day-heading::before {
            content: "";
            position: absolute;
            left: -34px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--wp-app-color-background);
            border: 2px solid var(--wp-app-color-border);
        }
        .timeline-day.current .day-heading::before { border-color: var(--wp-app-color-link); background: var(--wp-app-color-link); }
        .timeline-item-wrap {
            margin-bottom: 10px;
        }
        .timeline-item {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-background);
            color: inherit;
        }
        .timeline-item.current {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 1px;
        }
        .timeline-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .timeline-title-link {
            color: inherit;
            text-decoration: none;
        }
        .timeline-title-link:hover,
        .timeline-title-link:focus,
        .timeline-title-link:focus-visible {
            color: var(--wp-app-color-link);
            text-decoration: none;
        }
        .timeline-title-link:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .timeline-url-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            color: var(--wp-app-color-link);
            font-weight: 750;
            text-decoration: none;
        }
        .timeline-url-link:hover,
        .timeline-url-link:focus,
        .timeline-url-link:focus-visible {
            background: var(--wp-app-color-surface);
            text-decoration: none;
        }
        .timeline-url-link:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .time { color: var(--wp-app-color-muted); font-weight: 750; }
        .type { color: var(--wp-app-color-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0; }
        .timeline-meta {
            min-width: 0;
        }
        .title { font-weight: 750; overflow-wrap: anywhere; }
        .detail { color: var(--wp-app-color-muted); overflow-wrap: anywhere; }
        .detail a {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: inherit;
            text-decoration: none;
        }
        .detail a:hover,
        .detail a:focus,
        .detail a:focus-visible {
            color: var(--wp-app-color-link);
            text-decoration: none;
        }
        .detail a:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .timeline-item .detail,
        .summary-grid .detail {
            font-size: 0.88rem;
            line-height: 1.42;
        }
        .timeline-item .timeline-note {
            font-size: 0.8rem;
        }
        .attachment-links {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--wp-app-color-border);
        }
        .attachment-download {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            min-height: 30px;
            box-sizing: border-box;
            padding: 4px 8px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            color: var(--wp-app-color-text);
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.25;
            text-decoration: none;
        }
        .attachment-download:hover,
        .attachment-download:focus,
        .attachment-download:focus-visible {
            color: var(--wp-app-color-link);
            border-color: var(--wp-app-color-link);
            text-decoration: none;
        }
        .attachment-download:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .attachment-download span:last-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .url-preview {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--wp-app-color-border);
            color: inherit;
            text-decoration: none;
        }
        .url-preview:hover,
        .url-preview:focus,
        .url-preview:focus-visible,
        .url-preview:hover *,
        .url-preview:focus *,
        .url-preview:focus-visible * {
            text-decoration: none;
        }
        .url-preview:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .url-preview-image {
            width: 72px;
            aspect-ratio: 16 / 10;
            object-fit: cover;
            border-radius: 6px;
            background: var(--wp-app-color-surface);
        }
        .url-preview-title {
            font-size: 0.92rem;
            font-weight: 750;
            overflow-wrap: anywhere;
        }
        .url-preview-meta,
        .url-preview-description {
            color: var(--wp-app-color-muted);
            font-size: 0.82rem;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }
        .timeline-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .timeline-header h2 { margin: 0; }
        .lodging-checker {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--wp-app-color-muted);
            font: inherit;
            font-weight: inherit;
            line-height: inherit;
            text-decoration: none;
        }
        .lodging-checker-icon {
            color: #9a6700;
            font-weight: 800;
        }
        .lodging-checker.covered .lodging-checker-icon {
            color: #238636;
        }
        button.lodging-checker {
            cursor: pointer;
        }
        .lodging-checker-box {
            display: grid;
            gap: 10px;
            margin: -4px 0 14px;
            padding: 12px;
            border: 1px solid rgba(198, 139, 0, 0.32);
            border-radius: 8px;
            background: rgba(198, 139, 0, 0.08);
        }
        .lodging-checker-box.covered {
            border-color: rgba(35, 134, 54, 0.28);
            background: rgba(35, 134, 54, 0.08);
        }
        .lodging-checker-box[hidden] {
            display: none;
        }
        .lodging-checker-box-header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: var(--wp-app-color-muted);
            font-size: 0.88rem;
        }
        .lodging-checker-box-header strong {
            color: var(--wp-app-color-text);
        }
        .lodging-checker-night {
            display: grid;
            grid-template-columns: minmax(150px, 0.8fr) minmax(160px, 1fr);
            gap: 10px;
            align-items: center;
            padding: 8px 0;
            border-top: 1px solid rgba(198, 139, 0, 0.22);
        }
        .lodging-checker-night label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }
        .lodging-checker-night input[type="checkbox"] {
            width: auto;
        }
        .lodging-checker-night input[type="text"] {
            width: 100%;
        }
        .lodging-checker-night-covered {
            grid-template-columns: minmax(150px, 0.8fr) minmax(180px, 1fr) minmax(160px, 1fr);
        }
        .lodging-checker-night-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }
        .lodging-checker-night-status .lodging-checker-icon {
            color: #238636;
        }
        .lodging-checker-brief {
            color: var(--wp-app-color-muted);
            font-size: 0.88rem;
            overflow-wrap: anywhere;
        }
        .lodging-checker-actions {
            display: flex;
            justify-content: flex-end;
        }
        .add-item-button {
            margin-left: auto;
            min-height: 32px;
            padding: 5px 9px;
            font-size: 0.88rem;
            line-height: 1.2;
        }
        details.timeline-details,
        details.item {
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-background);
            margin-bottom: 10px;
        }
        .unscheduled-link {
            display: block;
            color: inherit;
            text-decoration: none;
            padding: 13px 14px;
        }
        .unscheduled-link:hover { border-color: var(--wp-app-color-link); }
        details.timeline-details[open],
        details.item[open] { background: var(--wp-app-color-surface); }
        details.timeline-details summary,
        details.item summary {
            cursor: pointer;
            list-style: none;
            padding: 13px 14px;
        }
        details.timeline-details summary::-webkit-details-marker,
        details.item summary::-webkit-details-marker { display: none; }
        .summary-grid {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }
        .edit-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding: 0 14px 14px;
            border-top: 1px solid var(--wp-app-color-border);
        }
        .add-item-form {
            margin-bottom: 18px;
            padding-top: 14px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-surface);
        }
        .add-item-panel {
            display: grid;
            gap: 12px;
            margin-bottom: 18px;
        }
        .add-item-panel[hidden] { display: none; }
        .add-item-panel details {
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-surface);
        }
        .add-item-panel summary {
            cursor: pointer;
            font-weight: 700;
            padding: 12px 14px;
        }
        .add-item-panel details .trip-import-form {
            margin-top: 0;
            padding: 0 14px 14px;
        }
        .field-wide { grid-column: 1 / -1; }
        .date-time-group {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .form-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; }
        .item-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .route-zone,
        .sharing-zone,
        .danger-zone {
            margin-top: 28px;
            border-top: 1px solid var(--wp-app-color-border);
            padding-top: 18px;
            color: var(--wp-app-color-muted);
        }
        .route-zone h2,
        .sharing-zone h2,
        .danger-zone h2 {
            color: var(--wp-app-color-text);
        }
        .route-zone details summary,
        .sharing-zone details summary,
        .danger-zone details summary {
            cursor: pointer;
            color: var(--wp-app-color-text);
            font-weight: 700;
        }
        .route-zone details summary h2,
        .sharing-zone details summary h2,
        .danger-zone details summary h2 {
            display: inline;
            margin: 0;
            font-size: 1.15rem;
        }
        .trip-import-form {
            display: grid;
            gap: 12px;
            margin-top: 14px;
        }
        .delete-button {
            background: transparent;
            color: #9f1f1f;
            border-color: rgba(159, 31, 31, 0.36);
        }
        .empty { color: var(--wp-app-color-muted); }
        @media (max-width: 680px) {
            .timeline-panel {
                background: transparent;
                border: 0;
                padding: 0;
            }
            .timeline-item {
                background: var(--wp-app-color-surface);
            }
            .timeline-item, .summary-grid, .edit-form, .share-option { grid-template-columns: 1fr; }
            .timeline-meta {
                display: flex;
                align-items: baseline;
                gap: 8px;
            }
            .url-preview { grid-template-columns: 1fr; }
            .url-preview-image { width: 100%; }
            .trip-title-header { align-items: flex-start; }
            .trip-title-form { grid-template-columns: 1fr; }
            .date-time-group { grid-template-columns: 1fr; }
            .timeline-header { flex-wrap: wrap; }
            .lodging-checker-night,
            .lodging-checker-night-covered { grid-template-columns: 1fr; }
            .lodging-checker-actions button { width: 100%; }
            .demo-controls label { min-width: 100%; }
        }
    </style>
</head>
<body>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_body_open(); ?>
    <?php endif; ?>

    <main>
        <?php if ( ! $is_readonly_timeline ) : ?>
            <div class="topbar">
                <a href="<?php echo esc_url( home_url( '/travel-app/' ) ); ?>"><?php esc_html_e( 'Back to Travel App', 'travel-app' ); ?></a>
            </div>
        <?php endif; ?>

        <?php if ( ! $is_readonly_timeline && null !== $trip_updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan updated.', 'travel-app' ); ?></div>
        <?php elseif ( ! $is_readonly_timeline && null !== $updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Itinerary item updated.', 'travel-app' ); ?></div>
        <?php elseif ( ! $is_readonly_timeline && $error ) : ?>
            <div class="notice error" role="alert"><?php echo esc_html( $travel_app->get_error_notice_message( $error ) ); ?></div>
        <?php endif; ?>

        <?php if ( ! $trip_data ) : ?>
            <section class="panel">
                <h1><?php esc_html_e( 'Travel plan not found', 'travel-app' ); ?></h1>
                <p class="empty"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'travel-app' ); ?></p>
            </section>
        <?php else : ?>
            <header>
                <div class="trip-title-header">
                    <h1><?php echo esc_html( $trip_data['title'] ); ?></h1>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <button class="trip-title-edit-button" type="button" data-trip-title-edit aria-controls="trip-title-form" aria-expanded="false" title="<?php esc_attr_e( 'Edit travel plan title', 'travel-app' ); ?>">
                            <span aria-hidden="true">✎</span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Edit travel plan title', 'travel-app' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( ! $is_readonly_timeline ) : ?>
                    <form class="trip-title-form" id="trip-title-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
                        <input type="hidden" name="action" value="travel_app_update_trip">
                        <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                        <?php wp_nonce_field( 'travel_app_update_trip_' . $trip_data['id'] ); ?>
                        <label for="trip_title">
                            <span class="screen-reader-text"><?php esc_html_e( 'Travel plan title', 'travel-app' ); ?></span>
                            <input type="text" id="trip_title" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>" required>
                        </label>
                        <button type="submit"><?php esc_html_e( 'Save', 'travel-app' ); ?></button>
                    </form>
                <?php endif; ?>
                <div class="meta">
                    <?php foreach ( $travel_app->get_trip_summary_parts( $trip_data, null, ! $is_static_download ) as $summary_part ) : ?>
                        <span><?php echo esc_html( $summary_part ); ?></span>
                    <?php endforeach; ?>
                    <span><?php echo esc_html( sprintf( _n( '%d item', '%d items', count( $segments ), 'travel-app' ), count( $segments ) ) ); ?></span>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <?php if ( ! empty( $lodging_required_nights ) && empty( $lodging_missing_ranges ) ) : ?>
                            <button class="lodging-checker covered" type="button" data-lodging-checker-toggle aria-controls="lodging-checker-box" aria-expanded="false">
                                <span class="lodging-checker-icon" aria-hidden="true">✓</span>
                                <span><?php esc_html_e( 'Lodging covered', 'travel-app' ); ?></span>
                            </button>
                        <?php elseif ( ! empty( $lodging_missing_ranges ) ) : ?>
                            <button class="lodging-checker" type="button" data-lodging-checker-toggle aria-controls="lodging-checker-box" aria-expanded="false">
                                <span class="lodging-checker-icon" aria-hidden="true">⚠</span>
                                <span>
                                    <?php
                                    printf(
                                        /* translators: %d: missing lodging night count. */
                                        esc_html( _n( '%d lodging night missing', '%d lodging nights missing', count( $missing_lodging_nights ), 'travel-app' ) ),
                                        count( $missing_lodging_nights )
                                    );
                                    ?>
                                </span>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </header>

            <section class="panel timeline-panel" aria-labelledby="timeline-heading" data-ai-assistant-important>
                <div class="timeline-header">
                    <h2 id="timeline-heading"><?php esc_html_e( 'Timeline', 'travel-app' ); ?></h2>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <button class="add-item-button" type="button" data-add-item-toggle aria-controls="add-item-form" aria-expanded="<?php echo ! empty( $quick_plan_segment ) ? 'true' : 'false'; ?>">
                            <?php esc_html_e( '+ Add Item', 'travel-app' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php
                $demo_control_id = 'trip-' . (string) $trip_data['id'];
                $demo_control_value = $demo_start_time;
                if ( ! $is_readonly_timeline && $demo_mode_enabled && ! $is_trip_active ) {
                    require __DIR__ . '/partials/demo-controls.php';
                }
                ?>

                <?php if ( ! $is_readonly_timeline && ! empty( $missing_lodging_night_details ) ) : ?>
                    <div class="lodging-checker-box" id="lodging-checker-box" data-lodging-checker-box hidden>
                        <div class="lodging-checker-box-header">
                            <span>
                                <strong><?php esc_html_e( 'Lodging missing', 'travel-app' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %d: missing lodging night count. */
                                    esc_html( _n( 'Review %d night without lodging.', 'Review %d nights without lodging.', count( $missing_lodging_nights ), 'travel-app' ) ),
                                    count( $missing_lodging_nights )
                                );
                                ?>
                            </span>
                        </div>
                        <?php foreach ( $missing_lodging_night_details as $night_index => $missing_lodging_night ) : ?>
                            <?php $night_input_id = 'missing-lodging-night-' . (string) $night_index; ?>
                            <div class="lodging-checker-night">
                                <label for="<?php echo esc_attr( $night_input_id ); ?>">
                                    <input
                                        id="<?php echo esc_attr( $night_input_id ); ?>"
                                        type="checkbox"
                                        data-lodging-night
                                        value="<?php echo esc_attr( (string) $missing_lodging_night['date'] ); ?>"
                                        checked
                                    >
                                    <span>
                                        <?php echo esc_html( $travel_app->format_date_label( (string) $missing_lodging_night['date'], false ) ); ?>
                                        <span aria-hidden="true">→</span>
                                        <?php echo esc_html( $travel_app->format_date_label( (string) $missing_lodging_night['end_date'] ) ); ?>
                                    </span>
                                </label>
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Location', 'travel-app' ); ?></span>
                                    <input
                                        type="text"
                                        data-lodging-night-location
                                        value="<?php echo esc_attr( (string) $missing_lodging_night['location'] ); ?>"
                                        placeholder="<?php esc_attr_e( 'Location', 'travel-app' ); ?>"
                                    >
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if ( empty( $quick_plan_segment ) ) : ?>
                            <div class="lodging-checker-actions">
                                <button type="button" data-lodging-prefill><?php esc_html_e( 'Add selected lodging', 'travel-app' ); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ( ! $is_readonly_timeline && ! empty( $covered_lodging_night_details ) ) : ?>
                    <div class="lodging-checker-box covered" id="lodging-checker-box" data-lodging-checker-box hidden>
                        <div class="lodging-checker-box-header">
                            <span>
                                <strong><?php esc_html_e( 'Lodging covered', 'travel-app' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %d: covered lodging night count. */
                                    esc_html( _n( 'Confirmed for %d night.', 'Confirmed for %d nights.', count( $covered_lodging_night_details ), 'travel-app' ) ),
                                    count( $covered_lodging_night_details )
                                );
                                ?>
                            </span>
                        </div>
                        <?php foreach ( $covered_lodging_night_details as $covered_lodging_night ) : ?>
                            <?php
                            $covered_item_type = (string) ( $covered_lodging_night['item_type'] ?? 'other' );
                            $covered_item_title = trim( (string) ( $covered_lodging_night['item_title'] ?? '' ) );
                            $covered_item_label = '' !== $covered_item_title
                                ? $covered_item_title
                                : ( $segment_type_labels[ $covered_item_type ] ?? __( 'Itinerary item', 'travel-app' ) );
                            ?>
                            <div class="lodging-checker-night lodging-checker-night-covered">
                                <span class="lodging-checker-night-status">
                                    <span class="lodging-checker-icon" aria-hidden="true">✓</span>
                                    <span>
                                        <?php echo esc_html( $travel_app->format_date_label( (string) $covered_lodging_night['date'], false ) ); ?>
                                        <span aria-hidden="true">→</span>
                                        <?php echo esc_html( $travel_app->format_date_label( (string) $covered_lodging_night['end_date'] ) ); ?>
                                    </span>
                                </span>
                                <span class="lodging-checker-brief">
                                    <?php echo esc_html( $covered_item_label ); ?>
                                    <?php if ( isset( $segment_type_labels[ $covered_item_type ] ) ) : ?>
                                        · <?php echo esc_html( $segment_type_labels[ $covered_item_type ] ); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="lodging-checker-brief"><?php echo esc_html( (string) ( $covered_lodging_night['location'] ?? '' ) ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! $is_readonly_timeline ) : ?>
                    <div class="add-item-panel" id="add-item-form" <?php echo empty( $quick_plan_segment ) ? 'hidden' : ''; ?>>
                        <?php
                        $quick_plan_parser_label = '';
                        $quick_plan_parser_error_code = '';
                        $quick_plan_parser_error_message = '';
                        if ( ! empty( $quick_plan_segment ) ) {
                            $quick_plan_parser = (string) ( $quick_plan_draft['parser'] ?? 'quick-plan' );
                            $quick_plan_parser_labels = [
                                'wp-ai-client' => __( 'AI extraction', 'travel-app' ),
                                'quick-plan'   => __( 'quick planner fallback', 'travel-app' ),
                                'fallback'     => __( 'basic parser fallback', 'travel-app' ),
                                'ics'          => __( 'calendar parser', 'travel-app' ),
                            ];
                            $quick_plan_parser_label = $quick_plan_parser_labels[ $quick_plan_parser ] ?? $quick_plan_parser;
                            $quick_plan_parser_error = isset( $quick_plan_draft['parser_error'] ) && is_array( $quick_plan_draft['parser_error'] )
                                ? $quick_plan_draft['parser_error']
                                : [];
                            $quick_plan_parser_error_code = (string) ( $quick_plan_parser_error['code'] ?? '' );
                            $quick_plan_parser_error_message = (string) ( $quick_plan_parser_error['message'] ?? '' );
                        }
                        ?>
                        <details>
                            <summary><?php esc_html_e( 'Import from Confirmation', 'travel-app' ); ?></summary>
                            <form class="trip-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="travel_app_import">
                                <input type="hidden" name="import_trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <?php wp_nonce_field( 'travel_app_import' ); ?>
                                <label for="trip_import_text">
                                    <?php
                                    printf(
                                        /* translators: %s: trip title. */
                                        esc_html__( 'Paste confirmation or plan for %s', 'travel-app' ),
                                        esc_html( $trip_data['title'] )
                                    );
                                    ?>
                                </label>
                                <textarea id="trip_import_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Paste itinerary text or a dated plan...', 'travel-app' ); ?>"></textarea>
                                <div class="form-actions">
                                    <button type="submit"><?php esc_html_e( 'Review Import', 'travel-app' ); ?></button>
                                </div>
                            </form>
                        </details>

                        <form class="edit-form add-item-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="<?php echo ! empty( $quick_plan_segment ) ? 'travel_app_import' : 'travel_app_add_segment'; ?>">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <?php if ( ! empty( $quick_plan_segment ) ) : ?>
                                <input type="hidden" name="import_trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <input type="hidden" name="quick_plan_draft" value="<?php echo esc_attr( $quick_plan_draft_key ); ?>">
                                <input type="hidden" name="quick_plan_target" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <?php wp_nonce_field( 'travel_app_import' ); ?>
                                <p class="empty field-wide">
                                    <?php
                                    printf(
                                        /* translators: %s: parser source label. */
                                        esc_html__( 'Prefilled from import. Review the fields before adding. Parsed with: %s.', 'travel-app' ),
                                        esc_html( $quick_plan_parser_label )
                                    );
                                    ?>
                                    <?php if ( '' !== $quick_plan_parser_error_code || '' !== $quick_plan_parser_error_message ) : ?>
                                        <?php
                                        printf(
                                            /* translators: 1: parser error code, 2: parser error message. */
                                            esc_html__( ' Parser error: %1$s %2$s', 'travel-app' ),
                                            esc_html( $quick_plan_parser_error_code ),
                                            esc_html( $quick_plan_parser_error_message )
                                        );
                                        ?>
                                    <?php endif; ?>
                                </p>
                            <?php else : ?>
                                <?php wp_nonce_field( 'travel_app_add_segment_' . $trip_data['id'] ); ?>
                            <?php endif; ?>
                            <label class="field-wide">
                                <?php esc_html_e( 'Title', 'travel-app' ); ?>
                                <input name="segment_title" value="<?php echo esc_attr( (string) ( $quick_plan_segment['title'] ?? '' ) ); ?>">
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'Type', 'travel-app' ); ?>
                                <select name="segment_type">
                                    <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $quick_plan_segment['type'] ?? 'activity', $type ); ?>><?php echo esc_html( $segment_type_labels[ $type ] ?? ucfirst( $type ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'URL', 'travel-app' ); ?>
                                <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $quick_plan_segment['url'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Location', 'travel-app' ); ?>
                                <input name="segment_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['location'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'End Location', 'travel-app' ); ?>
                                <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_location'] ?? '' ) ); ?>">
                            </label>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'Start Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'End Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <label class="field-wide">
                                <?php esc_html_e( 'Details', 'travel-app' ); ?>
                                <textarea name="segment_details"><?php echo esc_textarea( (string) ( $quick_plan_segment['details'] ?? '' ) ); ?></textarea>
                            </label>
                            <div class="form-actions">
                                <button type="submit"><?php echo esc_html( ! empty( $quick_plan_segment ) ? __( 'Add to This Trip', 'travel-app' ) : __( 'Add Item', 'travel-app' ) ); ?></button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ( empty( $segments_by_day ) ) : ?>
                    <p class="empty"><?php esc_html_e( 'No timeline items were found.', 'travel-app' ); ?></p>
                <?php else : ?>
                    <div class="timeline" id="timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>">
                        <div class="time-marker"><span class="time-marker-label"></span></div>
                        <?php foreach ( $segments_by_day as $day => $day_segments ) : ?>
                            <section class="timeline-day<?php echo empty( $day_segments ) ? ' empty' : ''; ?>" data-date="<?php echo esc_attr( $day ); ?>">
                                <h3 class="day-heading"><?php echo esc_html( $travel_app->format_date_label( $day ) ); ?></h3>
                                <?php foreach ( $day_segments as $segment ) : ?>
                                    <?php $index = (int) $segment['_index']; ?>
                                    <?php $timeline_kind = (string) ( $segment['_timeline_kind'] ?? 'start' ); ?>
                                    <?php $segment_anchor_suffix = in_array( $timeline_kind, [ 'checkout', 'return' ], true ) ? '-' . $timeline_kind : ''; ?>
                                    <?php $segment_anchor = 'segment-' . $index . $segment_anchor_suffix; ?>
                                    <?php $segment_datetime = trim( (string) ( $segment['date'] ?? '' ) . 'T' . ( (string) ( $segment['time'] ?? '' ) ?: '00:00' ) ); ?>
                                    <?php $segment_start_date = substr( trim( (string) ( $segment['date'] ?? '' ) ), 0, 10 ); ?>
                                    <?php $segment_end_date = substr( trim( (string) ( $segment['end_date'] ?? '' ) ), 0, 10 ); ?>
                                    <?php $is_end_timeline_entry = in_array( $timeline_kind, [ 'checkout', 'return' ], true ); ?>
                                    <?php $show_url_preview = ! $is_end_timeline_entry; ?>
                                    <?php $show_location = 'checkout' !== $timeline_kind && ( $show_private_share_details || $is_transport_segment( $segment ) ); ?>
                                    <?php $show_attachments = ! $is_end_timeline_entry && $show_private_share_details; ?>
                                    <?php
                                    if ( 'checkout' === $timeline_kind ) {
                                        $type_label = __( 'Check out', 'travel-app' );
                                    } elseif ( 'return' === $timeline_kind ) {
                                        $type_label = __( 'Return car', 'travel-app' );
                                    } elseif ( 'car' === ( $segment['type'] ?? '' ) ) {
                                        $type_label = __( 'Rental car', 'travel-app' );
                                    } else {
                                        $type_label = $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) );
                                    }
                                    ?>
                                    <?php $url_preview = isset( $segment['url_preview'] ) && is_array( $segment['url_preview'] ) ? $segment['url_preview'] : []; ?>
                                    <?php $attachments = $show_attachments && isset( $segment['attachments'] ) && is_array( $segment['attachments'] ) ? $segment['attachments'] : []; ?>
                                    <?php $has_url_preview = $show_url_preview && ! empty( $url_preview ) && ( ! empty( $url_preview['title'] ) || ! empty( $url_preview['description'] ) || ! empty( $url_preview['image'] ) ); ?>
                                    <div class="timeline-item-wrap" id="<?php echo esc_attr( $segment_anchor ); ?>">
                                        <div class="timeline-item" data-date="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>" data-datetime="<?php echo esc_attr( $segment_datetime ); ?>">
                                            <div class="timeline-meta">
                                                <div class="time"><?php echo esc_html( $segment['time'] ?: ' ' ); ?></div>
                                                <div class="type"><?php echo esc_html( $type_label ); ?></div>
                                            </div>
                                            <div>
                                                <div class="timeline-title-row title">
                                                    <?php if ( $is_readonly_timeline ) : ?>
                                                        <span><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                                    <?php else : ?>
                                                        <a class="timeline-title-link" href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_data['id'] . '/item/' . $index . '/' ) ); ?>">
                                                            <?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ( $show_url_preview && ! $has_url_preview && ! empty( $segment['url'] ) ) : ?>
                                                        <a class="timeline-url-link" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open item URL', 'travel-app' ); ?>">
                                                            <span aria-hidden="true">↗</span>
                                                            <span class="screen-reader-text"><?php esc_html_e( 'Open item URL', 'travel-app' ); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ( '' !== $segment_end_date && $segment_end_date !== $segment_start_date ) : ?>
                                                    <div class="detail"><?php echo esc_html( $travel_app->get_segment_date_range_label( $segment ) ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ! empty( $segment['location'] ) ) : ?>
                                                    <?php $location = (string) $segment['location']; ?>
                                                    <div class="detail">
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span aria-hidden="true">&#x1F4CD;</span>
                                                            <?php echo esc_html( $location ); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                    <?php $end_location = (string) $segment['end_location']; ?>
                                                    <div class="detail">
                                                        <?php esc_html_e( 'To:', 'travel-app' ); ?>
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span aria-hidden="true">&#x1F4CD;</span>
                                                            <?php echo esc_html( $end_location ); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_private_share_details && ! empty( $segment['details'] ) ) : ?>
                                                    <div class="detail timeline-note"><?php echo esc_html( $segment['details'] ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $attachments ) ) : ?>
                                                    <div class="attachment-links" aria-label="<?php esc_attr_e( 'Attachments', 'travel-app' ); ?>">
                                                        <?php foreach ( $attachments as $attachment ) : ?>
                                                            <?php
                                                            if ( empty( $attachment['url'] ) ) {
                                                                continue;
                                                            }
                                                            $attachment_label = (string) ( ( $attachment['title'] ?? '' ) ?: ( $attachment['filename'] ?? __( 'Attachment', 'travel-app' ) ) );
                                                            ?>
                                                            <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( sprintf( __( 'Download %s', 'travel-app' ), $attachment_label ) ); ?>">
                                                                <span aria-hidden="true">↓</span>
                                                                <span><?php echo esc_html( $attachment_label ); ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $has_url_preview ) : ?>
                                                    <a class="url-preview" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php if ( ! empty( $url_preview['image'] ) ) : ?>
                                                            <img class="url-preview-image" src="<?php echo esc_url( (string) $url_preview['image'] ); ?>" alt="" loading="lazy">
                                                        <?php endif; ?>
                                                        <div>
                                                            <?php if ( ! empty( $url_preview['site_name'] ) ) : ?>
                                                                <div class="url-preview-meta"><?php echo esc_html( (string) $url_preview['site_name'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['title'] ) ) : ?>
                                                                <div class="url-preview-title"><?php echo esc_html( (string) $url_preview['title'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['description'] ) ) : ?>
                                                                <div class="url-preview-description"><?php echo esc_html( (string) $url_preview['description'] ); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ( ! empty( $unscheduled_segments ) ) : ?>
                <section class="panel" aria-labelledby="items-heading">
                    <h2 id="items-heading"><?php esc_html_e( 'Unscheduled Items', 'travel-app' ); ?></h2>
                    <div>
                        <?php foreach ( $unscheduled_segments as $segment ) : ?>
                            <?php $index = (int) $segment['_index']; ?>
                            <?php $show_location = $show_private_share_details || $is_transport_segment( $segment ); ?>
                            <?php $attachments = $show_private_share_details && isset( $segment['attachments'] ) && is_array( $segment['attachments'] ) ? $segment['attachments'] : []; ?>
                            <div class="item unscheduled-link" id="segment-<?php echo esc_attr( (string) $index ); ?>">
                                <div class="summary-grid">
                                    <span class="time"><?php echo esc_html( trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) ) ); ?></span>
                                    <span>
                                        <span class="type"><?php echo esc_html( $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) ) ); ?></span><br>
                                        <?php if ( $is_readonly_timeline ) : ?>
                                            <span class="title"><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                        <?php else : ?>
                                            <a class="timeline-title-link title" href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_data['id'] . '/item/' . $index . '/' ) ); ?>">
                                                <?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $segment['end_date'] ) ) : ?>
                                            <br><span class="detail"><?php echo esc_html( $travel_app->get_segment_date_range_label( $segment ) ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $show_location && ! empty( $segment['location'] ) ) : ?>
                                            <?php $location = (string) $segment['location']; ?>
                                            <br><span class="detail">
                                                <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                    <span aria-hidden="true">&#x1F4CD;</span>
                                                    <?php echo esc_html( $location ); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                            <?php $end_location = (string) $segment['end_location']; ?>
                                            <br><span class="detail">
                                                <?php esc_html_e( 'To:', 'travel-app' ); ?>
                                                <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                    <span aria-hidden="true">&#x1F4CD;</span>
                                                    <?php echo esc_html( $end_location ); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $attachments ) ) : ?>
                                            <div class="attachment-links" aria-label="<?php esc_attr_e( 'Attachments', 'travel-app' ); ?>">
                                                <?php foreach ( $attachments as $attachment ) : ?>
                                                    <?php
                                                    if ( empty( $attachment['url'] ) ) {
                                                        continue;
                                                    }
                                                    $attachment_label = (string) ( ( $attachment['title'] ?? '' ) ?: ( $attachment['filename'] ?? __( 'Attachment', 'travel-app' ) ) );
                                                    ?>
                                                    <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( sprintf( __( 'Download %s', 'travel-app' ), $attachment_label ) ); ?>">
                                                        <span aria-hidden="true">↓</span>
                                                        <span><?php echo esc_html( $attachment_label ); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ( ! $is_readonly_timeline ) : ?>
                                        <a class="detail" href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_data['id'] . '/item/' . $index . '/' ) ); ?>"><?php esc_html_e( 'Open', 'travel-app' ); ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $show_private_share_details && ( ! empty( $trip_route_links ) || '' !== $trip_direct_map_url ) ) : ?>
                <section class="route-zone" aria-labelledby="route-maps-heading">
                    <details>
                        <summary><h2 id="route-maps-heading"><?php esc_html_e( 'Route Maps', 'travel-app' ); ?></h2></summary>
                        <div class="trip-actions" aria-label="<?php esc_attr_e( 'Route links', 'travel-app' ); ?>">
                            <?php if ( ! empty( $trip_route_links['google'] ) ) : ?>
                                <a class="ghost-button" href="<?php echo esc_url( (string) $trip_route_links['google'] ); ?>" target="_blank" rel="noopener noreferrer">
                                    <span aria-hidden="true">&#x1F5FA;</span>
                                    <?php esc_html_e( 'Google Maps', 'travel-app' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( '' !== $trip_direct_map_url ) : ?>
                                <a class="ghost-button" href="<?php echo esc_url( $trip_direct_map_url ); ?>">
                                    <span aria-hidden="true">&#x1F5FA;</span>
                                    <?php esc_html_e( 'OpenStreetMap', 'travel-app' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="sharing-zone" aria-labelledby="sharing-heading" data-share-control data-trip-id="<?php echo esc_attr( (string) $trip_data['id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'travel_app_share_link_' . $trip_data['id'] ) ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                    <details>
                        <summary><h2 id="sharing-heading"><?php esc_html_e( 'Sharing', 'travel-app' ); ?></h2></summary>
                        <div class="share-link">
                            <div class="share-option">
                                <span>
                                    <strong><?php esc_html_e( 'Fellow travellers', 'travel-app' ); ?></strong><br>
                                    <span class="empty"><?php esc_html_e( 'Includes addresses and attachments.', 'travel-app' ); ?></span>
                                </span>
                                <span class="share-actions">
                                    <a class="ghost-button" href="<?php echo esc_url( $travel_app->get_trip_html_download_url( (int) $trip_data['id'], 'fellow' ) ); ?>">
                                        <?php esc_html_e( 'Download', 'travel-app' ); ?>
                                    </a>
                                    <?php if ( ! $travel_app->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-mode="fellow" data-share-url="<?php echo esc_attr( $fellow_share_url ); ?>"><?php esc_html_e( 'Copy', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="fellow" <?php echo '' === $fellow_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove', 'travel-app' ); ?></button>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="share-option">
                                <span>
                                    <strong><?php esc_html_e( 'Others', 'travel-app' ); ?></strong><br>
                                    <span class="empty"><?php esc_html_e( 'Shows transport start and end locations; hides other addresses and attachments.', 'travel-app' ); ?></span>
                                </span>
                                <span class="share-actions">
                                    <a class="ghost-button" href="<?php echo esc_url( $travel_app->get_trip_html_download_url( (int) $trip_data['id'], 'public' ) ); ?>">
                                        <?php esc_html_e( 'Download', 'travel-app' ); ?>
                                    </a>
                                    <?php if ( ! $travel_app->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-mode="public" data-share-url="<?php echo esc_attr( $public_share_url ); ?>"><?php esc_html_e( 'Copy', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="public" <?php echo '' === $public_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Remove', 'travel-app' ); ?></button>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php if ( ! $travel_app->is_playground() ) : ?>
                            <p class="empty" data-share-status aria-live="polite"></p>
                        <?php endif; ?>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="danger-zone" aria-labelledby="delete-heading">
                    <details>
                        <summary><h2 id="delete-heading"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></h2></summary>
                        <p><?php esc_html_e( 'This deletes the travel plan and moves its itinerary items to the trash.', 'travel-app' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this travel plan?', 'travel-app' ) ); ?>');">
                            <input type="hidden" name="action" value="travel_app_delete">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <?php wp_nonce_field( 'travel_app_delete_' . $trip_data['id'] ); ?>
                            <button class="delete-button" type="submit"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></button>
                        </form>
                    </details>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ( ! $is_readonly_timeline ) : ?>
        <script>
            (function() {
                var button = document.querySelector('[data-trip-title-edit]');
                var form = document.getElementById('trip-title-form');

                if (!button || !form) {
                    return;
                }

                button.addEventListener('click', function() {
                    var titleInput = form.querySelector('input[name="trip_title"]');
                    var isHidden = form.hasAttribute('hidden');

                    if (isHidden) {
                        form.removeAttribute('hidden');
                        button.setAttribute('aria-expanded', 'true');

                        if (titleInput) {
                            titleInput.focus();
                            titleInput.select();
                        }

                        return;
                    }

                    form.setAttribute('hidden', '');
                    button.setAttribute('aria-expanded', 'false');
                });
            })();

            (function() {
                var button = document.querySelector('[data-add-item-toggle]');
                var form = document.getElementById('add-item-form');

                if (!button || !form) {
                    return;
                }

                button.addEventListener('click', function() {
                    var titleInput = form.querySelector('input[name="segment_title"]');
                    var isHidden = form.hasAttribute('hidden');

                    if (isHidden) {
                        form.removeAttribute('hidden');
                        button.setAttribute('aria-expanded', 'true');

                        if (titleInput) {
                            titleInput.focus();
                        }

                        return;
                    }

                    form.setAttribute('hidden', '');
                    button.setAttribute('aria-expanded', 'false');
                });

                document.querySelectorAll('[data-lodging-prefill]').forEach(function(prefillButton) {
                    prefillButton.addEventListener('click', function() {
                        function nextDateValue(dateValue) {
                            var parts = dateValue.split('-').map(function(part) {
                                return parseInt(part, 10);
                            });
                            var date = new Date(parts[0], parts[1] - 1, parts[2] + 1);
                            var month = String(date.getMonth() + 1).padStart(2, '0');
                            var day = String(date.getDate()).padStart(2, '0');

                            return [date.getFullYear(), month, day].join('-');
                        }

                        function dateValueDays(dateValue) {
                            var parts = dateValue.split('-').map(function(part) {
                                return parseInt(part, 10);
                            });

                            return Math.floor(Date.UTC(parts[0], parts[1] - 1, parts[2]) / 86400000);
                        }

                        var titleInput = form.querySelector('input[name="segment_title"]');
                        var typeInput = form.querySelector('[name="segment_type"]');
                        var locationInput = form.querySelector('input[name="segment_location"]');
                        var startInput = form.querySelector('input[name="segment_date"]');
                        var endInput = form.querySelector('input[name="segment_end_date"]');
                        var detailsInput = form.querySelector('textarea[name="segment_details"]');
                        var checkerBox = prefillButton.closest('[data-lodging-checker-box]');
                        var selectedNights = checkerBox
                            ? Array.prototype.slice.call(checkerBox.querySelectorAll('[data-lodging-night]:checked'))
                            : [];

                        if (!selectedNights.length) {
                            return;
                        }

                        var selected = selectedNights.map(function(nightInput) {
                            var row = nightInput.closest('.lodging-checker-night');
                            var rowLocation = row ? row.querySelector('[data-lodging-night-location]') : null;
                            return {
                                date: nightInput.value || '',
                                location: rowLocation ? rowLocation.value.trim() : ''
                            };
                        }).filter(function(night) {
                            return night.date;
                        }).sort(function(a, b) {
                            return a.date.localeCompare(b.date);
                        });

                        if (!selected.length) {
                            return;
                        }

                        var hasGap = selected.some(function(night, index) {
                            return index > 0 && dateValueDays(night.date) - dateValueDays(selected[index - 1].date) !== 1;
                        });

                        if (hasGap) {
                            window.alert('<?php echo esc_js( __( 'Select one continuous lodging date range.', 'travel-app' ) ); ?>');
                            return;
                        }

                        var startDate = selected[0].date;
                        var lastDate = selected[selected.length - 1].date;
                        var endDate = nextDateValue(lastDate);
                        var locations = selected.map(function(night) {
                            return night.location;
                        }).filter(Boolean);
                        var uniqueLocations = locations.filter(function(location, index) {
                            return locations.indexOf(location) === index;
                        });

                        form.removeAttribute('hidden');
                        button.setAttribute('aria-expanded', 'true');

                        if (typeInput) {
                            typeInput.value = 'lodging';
                        }
                        if (startInput) {
                            startInput.value = startDate;
                        }
                        if (endInput) {
                            endInput.value = endDate;
                        }
                        if (locationInput) {
                            locationInput.value = locations[0] || '';
                        }
                        if (detailsInput && uniqueLocations.length > 1) {
                            detailsInput.value = selected.map(function(night) {
                                return night.date + (night.location ? ': ' + night.location : '');
                            }).join('\n');
                        } else if (detailsInput && detailsInput.value.indexOf(': ') !== -1) {
                            detailsInput.value = '';
                        }
                        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        if (titleInput) {
                            titleInput.focus();
                            titleInput.select();
                        }
                    });
                });

                var lodgingCheckerToggle = document.querySelector('[data-lodging-checker-toggle]');
                var lodgingCheckerBox = document.querySelector('[data-lodging-checker-box]');

                if (lodgingCheckerToggle && lodgingCheckerBox) {
                    lodgingCheckerToggle.addEventListener('click', function() {
                        var isHidden = lodgingCheckerBox.hasAttribute('hidden');

                        if (isHidden) {
                            lodgingCheckerBox.removeAttribute('hidden');
                            lodgingCheckerToggle.setAttribute('aria-expanded', 'true');
                            return;
                        }

                        lodgingCheckerBox.setAttribute('hidden', '');
                        lodgingCheckerToggle.setAttribute('aria-expanded', 'false');
                    });
                }
            })();

            (function() {
                var control = document.querySelector('[data-share-control]');

                if (!control) {
                    return;
                }

                var copyButtons = Array.prototype.slice.call(control.querySelectorAll('[data-share-copy]'));
                var removeButtons = Array.prototype.slice.call(control.querySelectorAll('[data-share-remove]'));
                var primaryCopyButton = copyButtons[0] || null;
                var status = control.querySelector('[data-share-status]');
                var defaultCopyText = primaryCopyButton ? primaryCopyButton.textContent : '';
                var copyResetTimers = {};

                function setStatus(message) {
                    if (status) {
                        status.textContent = message || '';
                    }
                }

                function setBusy(isBusy) {
                    removeButtons.concat(copyButtons).forEach(function(button) {
                        if (button) {
                            button.disabled = isBusy;
                        }
                    });
                }

                function setShareUrl(mode, url) {
                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.setAttribute('data-share-url', url || '');
                        }
                    });

                    removeButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.hidden = !url;
                        }
                    });

                    resetCopyButton(mode);
                }

                function resetCopyButton(mode) {
                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.textContent = defaultCopyText;
                            button.classList.remove('copied');
                        }
                    });
                }

                function confirmCopied(mode) {
                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.textContent = '<?php echo esc_js( __( 'Copied!', 'travel-app' ) ); ?>';
                            button.classList.add('copied');
                        }
                    });
                    setStatus('<?php echo esc_js( __( 'Share link copied.', 'travel-app' ) ); ?>');

                    if (copyResetTimers[mode]) {
                        window.clearTimeout(copyResetTimers[mode]);
                    }

                    copyResetTimers[mode] = window.setTimeout(function() {
                        resetCopyButton(mode);
                    }, 1800);
                }

                function requestShareAction(action, mode) {
                    var body = new URLSearchParams();
                    body.set('action', action);
                    body.set('trip_id', control.getAttribute('data-trip-id') || '');
                    body.set('nonce', control.getAttribute('data-nonce') || '');
                    if (mode) {
                        body.set('share_mode', mode);
                    }

                    setBusy(true);
                    setStatus('');

                    return fetch(control.getAttribute('data-ajax-url') || '', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: body.toString()
                    }).then(function(response) {
                        return response.json().then(function(data) {
                            if (!response.ok || !data || !data.success) {
                                throw new Error(data && data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'The sharing change could not be saved.', 'travel-app' ) ); ?>');
                            }

                            return data.data || {};
                        });
                    }).then(function(data) {
                        if (data.mode) {
                            setShareUrl(data.mode, data.url || '');
                        }
                        setStatus(data.message || '');
                        return data;
                    }).catch(function(error) {
                        setStatus(error.message || '<?php echo esc_js( __( 'The sharing change could not be saved.', 'travel-app' ) ); ?>');
                        throw error;
                    }).finally(function() {
                        setBusy(false);
                    });
                }

                removeButtons.forEach(function(removeButton) {
                    removeButton.addEventListener('click', function() {
                        requestShareAction('travel_app_remove_share_link', removeButton.getAttribute('data-share-mode') || 'fellow');
                    });
                });

                function copyShareUrl(url, mode) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        return navigator.clipboard.writeText(url).then(function() {
                            confirmCopied(mode);
                        }).catch(function() {
                            window.prompt('<?php echo esc_js( __( 'Copy this share link:', 'travel-app' ) ); ?>', url);
                            confirmCopied(mode);
                        });
                    }

                    window.prompt('<?php echo esc_js( __( 'Copy this share link:', 'travel-app' ) ); ?>', url);
                    confirmCopied(mode);
                    return Promise.resolve();
                }

                copyButtons.forEach(function(copyButton) {
                    copyButton.addEventListener('click', function() {
                        var mode = copyButton.getAttribute('data-share-mode') || 'fellow';
                        var url = copyButton.getAttribute('data-share-url') || '';

                        if (url) {
                            copyShareUrl(url, mode);
                            return;
                        }

                        copyButton.textContent = '<?php echo esc_js( __( 'Generating...', 'travel-app' ) ); ?>';
                        requestShareAction('travel_app_generate_share_link', mode).then(function(data) {
                            if (data && data.url) {
                                copyShareUrl(data.url, mode);
                                return;
                            }

                            resetCopyButton(mode);
                        }).catch(function() {
                            resetCopyButton(mode);
                        });
                    });
                });
            })();
        </script>
    <?php endif; ?>

    <?php if ( $is_static_download ) : ?>
        <?php $static_timeline_script = $travel_app->get_static_timeline_script(); ?>
        <?php if ( '' !== $static_timeline_script ) : ?>
            <script>
                <?php echo $static_timeline_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </script>
        <?php endif; ?>
    <?php else : ?>
        <?php wp_app_body_close(); ?>
    <?php endif; ?>
</body>
</html>
