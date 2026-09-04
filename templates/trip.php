<?php
use Traveler\App;
use Traveler\LodgingCoverage;
use Traveler\Parser\AiParser;
use Traveler\Trip;

global $wp_app_route;

$traveler = App::get_instance();
$demo_mode_enabled = $traveler->is_demo_mode_enabled();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$share_token = isset( $wp_app_route['params']['token'] ) ? sanitize_text_field( wp_unslash( $wp_app_route['params']['token'] ) ) : '';
$is_static_download = ! empty( $traveler_static_download );
$is_shared_timeline = ! empty( $traveler_shared_timeline ) || '' !== $share_token;
$is_readonly_timeline = $is_shared_timeline || $is_static_download;
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_traveler_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'traveler' ),
        esc_html__( 'Travel plan not found', 'traveler' ),
        [ 'response' => 404 ]
    );
}
$share_mode = $is_static_download ? ( isset( $traveler_static_share_mode ) ? (string) $traveler_static_share_mode : 'fellow' ) : ( $is_shared_timeline ? $traveler->get_trip_share_mode_by_token( $trip_id, $share_token ) : '' );
$show_private_share_details = ( ! $is_shared_timeline && ! $is_static_download ) || 'fellow' === $share_mode;
$error      = isset( $_GET['traveler_error'] ) ? sanitize_key( wp_unslash( $_GET['traveler_error'] ) ) : '';
$quick_plan_draft_key = isset( $_GET['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_GET['quick_plan_draft'] ) ) : '';
$quick_plan_draft = '' !== $quick_plan_draft_key ? $traveler->get_quick_plan_draft( $quick_plan_draft_key ) : [];
$quick_plan_draft_target = isset( $quick_plan_draft['target_trip_id'] ) ? absint( $quick_plan_draft['target_trip_id'] ) : 0;
$quick_plan_segment = $quick_plan_draft_target === $trip_id && isset( $quick_plan_draft['segment'] ) && is_array( $quick_plan_draft['segment'] )
    ? $quick_plan_draft['segment']
    : [];
$has_ai = AiParser::is_available();

$segments_user_id = null;
if ( $is_shared_timeline ) {
    $segments_user_id = Trip::get_owner_id( $trip_id );
}
$trip_data = $trip->with_segments_user_id( $segments_user_id )->to_array();
$segments  = $trip_data['segments'] ?? [];
$traveller_label = $traveler->get_trip_traveller_label( $trip_data );
$editable_trip_data = [];
if ( ! $is_readonly_timeline ) {
    $editable_trip_data = $trip_data;
    $editable_trip_data['segments'] = array_map( static function( array $editable_segment ) use ( $trip_data ): array {
        $editable_index = (int) ( $editable_segment['id'] ?? 0 );
        $editable_segment['edit_nonce'] = wp_create_nonce( 'traveler_update_segment_' . (int) $trip_data['id'] . '_' . $editable_index );
        $editable_segment['delete_nonce'] = wp_create_nonce( 'traveler_delete_segment_' . (int) $trip_data['id'] . '_' . $editable_index );

        return $editable_segment;
    }, $segments );
}
$is_trip_active = $traveler->is_trip_active( $trip_data );
$show_now_next_section = '0' !== (string) get_term_meta( $trip_id, '_traveler_show_now_next', true );
$journal_enabled = '1' === (string) get_term_meta( $trip_id, '_traveler_journal_enabled', true );
$journal_entries_by_day = ( ! $is_readonly_timeline && $journal_enabled ) ? $traveler->get_journal_entries_for_trip( $trip_id ) : [];
$journal_category_id = absint( get_term_meta( $trip_id, '_traveler_journal_category_id', true ) );
$journal_tags = (string) get_term_meta( $trip_id, '_traveler_journal_tags', true );
$can_manage_trip_editors = ! $is_readonly_timeline && $traveler->current_user_can_manage_trip_editors( $trip_id );
$trip_editor_ids = $can_manage_trip_editors ? $traveler->get_trip_editor_ids( $trip_id ) : [];
$trip_editor_candidates = $can_manage_trip_editors ? $traveler->get_trip_editor_candidates( $trip_id ) : [];
$journal_categories = ! $is_readonly_timeline ? get_categories( [
    'hide_empty' => false,
] ) : [];
$fellow_share_url = ! $is_shared_timeline ? $traveler->get_trip_share_url( (int) $trip_data['id'], 'fellow' ) : '';
$public_share_url = ! $is_shared_timeline ? $traveler->get_trip_share_url( (int) $trip_data['id'], 'public' ) : '';
$fellow_calendar_url = ! $is_shared_timeline ? $traveler->get_trip_calendar_url( (int) $trip_data['id'], 'fellow' ) : '';
$public_calendar_url = ! $is_shared_timeline ? $traveler->get_trip_calendar_url( (int) $trip_data['id'], 'public' ) : '';
$segment_type_labels = [
    'flight'   => __( 'Flight', 'traveler' ),
    'lodging'  => __( 'Lodging', 'traveler' ),
    'train'    => __( 'Train', 'traveler' ),
    'car'      => __( 'Rental car', 'traveler' ),
    'activity' => __( 'Activity', 'traveler' ),
    'other'    => __( 'Other', 'traveler' ),
];
$timeline_type_codes = [
    'flight'   => 'FL',
    'lodging'  => 'ST',
    'train'    => 'TR',
    'car'      => 'DR',
    'activity' => 'DO',
    'other'    => 'IT',
];
$lodging_coverage = LodgingCoverage::analyze( $trip_data, $segments );
$timeline_segments = LodgingCoverage::timeline_segments( $segments );
if ( $is_readonly_timeline ) {
    $timeline_segments = array_values(
        array_filter(
            $timeline_segments,
            static function( array $timeline_segment ): bool {
                return 'checkout' !== ( $timeline_segment['_timeline_kind'] ?? '' );
            }
        )
    );
}
$lodging_required_nights = $lodging_coverage['required_nights'];
$covered_lodging_night_details = $lodging_coverage['covered_details'];
$missing_lodging_nights = $lodging_coverage['missing_nights'];
$lodging_missing_ranges = $lodging_coverage['missing_ranges'];
$missing_lodging_night_details = $lodging_coverage['missing_details'];

foreach ( $timeline_segments as &$timeline_segment ) {
    if ( 'checkout' === ( $timeline_segment['_timeline_kind'] ?? '' ) && '' === (string) ( $timeline_segment['title'] ?? '' ) ) {
        $timeline_segment['title'] = __( 'Lodging', 'traveler' );
    }

    if ( 'return' === ( $timeline_segment['_timeline_kind'] ?? '' ) && '' === (string) ( $timeline_segment['title'] ?? '' ) ) {
        $timeline_segment['title'] = __( 'Rental car', 'traveler' );
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
$timeline_current_time_value = current_time( 'Y-m-d\TH:i' );
$timeline_current_time_captured = (string) time();
$trip_end_date = (string) ( $trip_data['ends_at'] ?? '' );
$is_trip_past = '' !== $trip_end_date && $trip_end_date < $today;
if ( $is_trip_active && '' !== $today && ! isset( $segments_by_day[ $today ] ) ) {
    $segments_by_day[ $today ] = [];
    ksort( $segments_by_day );
}

$demo_start = $trip_data['starts_at'] ?? '';
if ( '' === $demo_start ) {
    $demo_start = gmdate( 'Y-m-d' );
}
$demo_start_time = $demo_start . 'T12:00';
$show_timeline_demo_controls = ! $is_readonly_timeline && $demo_mode_enabled && ! $is_trip_active && ! $is_trip_past;
$show_timeline_time_marker = $is_trip_active || $show_timeline_demo_controls;

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
    $trip_direct_map_url = $is_static_download ? '' : home_url( '/traveler/trip/' . (int) $trip_data['id'] . '/map/' );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( $trip_data ? $trip_data['title'] : __( 'Travel Plan', 'traveler' ) ); ?></title>
    <?php if ( ! $is_static_download ) : ?>
        <link rel="manifest" href="<?php echo esc_url( $traveler->get_manifest_url( (int) $trip_data['id'], $share_token ) ); ?>">
        <meta name="theme-color" content="#0b6bcb">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $trip_data['title'] ?: __( 'Timeline', 'traveler' ) ); ?>">
    <?php endif; ?>
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
        main { max-width: 1180px; margin: 0 auto; padding: 32px 18px 56px; }
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
        .bottom-nav { margin-top: 24px; }
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
            border: 0;
            background: transparent;
            color: var(--wp-app-color-link);
        }
        .trip-title-edit-button span[aria-hidden="true"] {
            display: inline-block;
            transform: scaleX(-1);
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
            color: var(--wp-app-color-text);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .share-link .ghost-button[hidden] { display: none; }
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
        .offline-status[hidden] { display: none; }
        .offline-status {
            margin-bottom: 18px;
            border-radius: 6px;
            padding: 10px 12px;
            border: 1px solid rgba(11, 107, 203, 0.28);
            background: rgba(11, 107, 203, 0.08);
            color: var(--wp-app-color-text);
        }
        .offline-status.error { border-color: rgba(138, 75, 8, 0.28); background: rgba(138, 75, 8, 0.08); }
        .offline-panel {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--wp-app-color-border);
            color: var(--wp-app-color-muted);
        }
        .offline-panel summary {
            cursor: pointer;
        }
        .offline-panel summary h2 {
            display: inline;
        }
        .offline-panel h2 {
            margin-bottom: 10px;
            color: var(--wp-app-color-text);
        }
        .offline-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .offline-grid div {
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 10px 12px;
            background: var(--wp-app-color-surface);
        }
        .offline-grid dt {
            margin: 0 0 3px;
            font-size: 0.82rem;
        }
        .offline-grid dd {
            margin: 0;
            color: var(--wp-app-color-text);
            font-weight: 750;
            overflow-wrap: anywhere;
        }
        .demo-controls { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; margin-bottom: 18px; }
        .demo-controls label { min-width: 190px; margin: 0; }
        .ghost-button {
            background: transparent;
            color: var(--wp-app-color-text);
            border: 1px solid var(--wp-app-color-border);
            border-color: var(--wp-app-color-border);
        }
        .timeline-panel,
        .now-next-panel {
            --ledger-ink: #101820;
            --ledger-paper: #f7f8f5;
            --ledger-muted: #52606b;
            --ledger-line: #ccd2d5;
            --ledger-signal: #e4532f;
            --ledger-current: #eaf0eb;
        }
        .timeline-panel {
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            padding: 0;
            border-radius: 0;
            background: var(--ledger-paper);
            color: var(--ledger-ink);
        }
        .timeline-day-rail {
            position: sticky;
            top: 18px;
            align-self: start;
            min-height: calc(100vh - 36px);
            box-sizing: border-box;
            padding: 26px 18px;
            background: var(--ledger-ink);
            color: var(--ledger-paper);
        }
        .timeline-day-rail-title {
            display: block;
            margin-bottom: 6px;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.025em;
        }
        .timeline-day-rail-summary {
            margin: 0 0 24px;
            color: var(--ledger-line);
            font-size: 0.78rem;
        }
        .timeline-day-links {
            display: grid;
            gap: 3px;
        }
        .timeline-day-link {
            display: grid;
            grid-template-columns: 32px minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            min-height: 48px;
            box-sizing: border-box;
            padding: 7px 8px;
            border-radius: 6px;
            color: inherit;
            text-decoration: none;
        }
        .timeline-day-link:hover,
        .timeline-day-link:focus,
        .timeline-day-link:focus-visible,
        .timeline-day-link.is-active {
            background: rgba(247, 248, 245, 0.12);
            color: inherit;
            text-decoration: none;
        }
        .timeline-day-link.is-current,
        .timeline-day-link[aria-current="date"] {
            background: var(--ledger-signal);
            color: #101820;
        }
        .timeline-day-link:focus-visible {
            outline: 2px solid var(--ledger-paper);
            outline-offset: 2px;
        }
        .timeline-day-link strong {
            font-size: 1.15rem;
            font-variant-numeric: tabular-nums;
        }
        .timeline-day-link span,
        .timeline-day-link small {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .timeline-day-link span { font-size: 0.77rem; }
        .timeline-day-link small { font-size: 0.7rem; opacity: 0.72; }
        .timeline-day-link.is-current small,
        .timeline-day-link[aria-current="date"] small { opacity: 1; }
        .timeline-ledger-content {
            min-width: 0;
            padding: 26px 30px 44px;
        }
        .timeline { position: relative; display: grid; gap: 0; }
        .timeline-day {
            position: relative;
            margin-bottom: 44px;
            scroll-margin-top: 22px;
        }
        .timeline-day:last-child { margin-bottom: 0; }
        .timeline-day-content[hidden] { display: none; }
        .timeline-day-empty {
            margin: 0;
            padding: 26px 4px;
            border-bottom: 1px dashed var(--ledger-line);
            color: var(--ledger-muted);
            font-size: 0.82rem;
        }
        .time-marker {
            display: none;
            position: absolute;
            z-index: 3;
            left: 0;
            right: 0;
            width: auto;
            height: 0;
            border-top: 1px solid var(--ledger-signal);
            color: var(--ledger-signal);
            pointer-events: none;
        }
        .time-marker::before {
            content: "";
            position: absolute;
            left: 65px;
            top: -5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--ledger-signal);
            box-shadow: 0 0 0 3px var(--ledger-paper);
        }
        .time-marker span {
            position: absolute;
            left: 58px;
            top: -13px;
            transform: translateX(-100%);
            padding: 2px 6px;
            border-radius: 999px;
            background: var(--ledger-signal);
            color: var(--ledger-ink);
            font-size: 0.76rem;
            font-weight: 750;
            line-height: 1.2;
            white-space: nowrap;
        }
        .day-heading {
            margin: 0;
            color: var(--ledger-muted);
            font-size: 0.88rem;
            font-weight: 700;
        }
        .day-heading-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: end;
            justify-content: space-between;
            padding-bottom: 11px;
            border-bottom: 3px solid var(--ledger-ink);
        }
        .day-heading-copy {
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr);
            gap: 14px;
            align-items: end;
        }
        .day-number {
            font-size: 2.65rem;
            font-weight: 850;
            line-height: 0.8;
            letter-spacing: -0.035em;
            font-variant-numeric: tabular-nums;
        }
        .day-heading-meta { min-width: 0; }
        .day-item-count {
            display: block;
            margin-top: 2px;
            color: var(--ledger-muted);
            font-size: 0.75rem;
        }
        .day-heading-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
        }
        .day-journal-form {
            margin: 0;
        }
        .day-journal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }
        .day-journal-button {
            min-height: 30px;
            padding: 4px 9px;
            border-color: var(--ledger-line);
            border-radius: 4px;
            background: transparent;
            color: var(--ledger-ink);
            font-size: 0.82rem;
            font-weight: 700;
        }
        .day-collapse-button {
            min-height: 36px;
            padding: 4px 2px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: var(--ledger-ink);
            font-size: 0.76rem;
            font-weight: 750;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .day-collapse-button:focus-visible {
            outline: 2px solid var(--ledger-signal);
            outline-offset: 2px;
        }
        .timeline-item-wrap {
            margin: 0;
        }
        .timeline-item {
            display: grid;
            grid-template-columns: 68px 42px minmax(190px, 1fr) auto;
            gap: 14px;
            align-items: center;
            min-height: 76px;
            box-sizing: border-box;
            padding: 12px 10px;
            border: 0;
            border-bottom: 1px solid var(--ledger-line);
            border-radius: 0;
            background: transparent;
            color: inherit;
        }
        .timeline-item.past { color: #67737a; }
        .timeline-item.current {
            background: var(--ledger-current);
            color: var(--ledger-ink);
            outline: 0;
        }
        .timeline-item.current .timeline-symbol { background: var(--ledger-signal); color: #101820; }
        .timeline-symbol {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 4px;
            background: var(--ledger-ink);
            color: var(--ledger-paper);
            font-size: 0.67rem;
            font-weight: 850;
            letter-spacing: 0.04em;
        }
        .timeline-event { min-width: 0; }
        .timeline-state {
            min-width: 76px;
            box-sizing: border-box;
            padding: 5px 8px;
            border: 1px solid var(--ledger-line);
            border-radius: 4px;
            color: var(--ledger-muted);
            font-size: 0.69rem;
            font-weight: 800;
            letter-spacing: 0.045em;
            text-align: center;
            text-transform: uppercase;
        }
        .timeline-item.current .timeline-state {
            border-color: var(--ledger-signal);
            color: #942b14;
        }
        .timeline-state.generated { border-style: dashed; }
        .timeline-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .timeline-title-link {
            color: inherit;
            text-decoration: none;
        }
        .timeline-title-button {
            appearance: none;
            min-height: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            font-weight: inherit;
            text-align: left;
            cursor: pointer;
        }
        .timeline-title-link:hover,
        .timeline-title-link:focus,
        .timeline-title-link:focus-visible,
        .timeline-title-button:hover,
        .timeline-title-button:focus,
        .timeline-title-button:focus-visible {
            color: var(--wp-app-color-link);
            text-decoration: none;
        }
        .timeline-title-link:focus-visible,
        .timeline-title-button:focus-visible {
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
        .time {
            color: var(--ledger-muted);
            font-size: 0.82rem;
            font-weight: 750;
            font-variant-numeric: tabular-nums;
        }
        .type { color: var(--wp-app-color-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0; }
        .timeline-meta {
            min-width: 0;
        }
        .title { font-weight: 750; overflow-wrap: anywhere; }
        .detail { color: var(--ledger-muted); overflow-wrap: anywhere; }
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
        .attachment-download-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1em;
            flex: 0 0 1em;
        }
        .attachment-download span:nth-child(2) {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .attachment-offline-indicator {
            flex: 0 0 auto;
            color: #237a3b;
            font-weight: 900;
            line-height: 1;
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
        .url-preview.no-image {
            grid-template-columns: minmax(0, 1fr);
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
        .url-preview-text {
            min-width: 0;
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
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 24px;
        }
        .timeline-header h2 {
            margin: 0;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }
        .timeline-header-copy p {
            margin: 3px 0 0;
            color: var(--ledger-muted);
            font-size: 0.8rem;
        }
        .timeline-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-left: auto;
        }
        .now-next-panel {
            padding: 0;
            border: 0;
            border-radius: 0;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .mini-timeline {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        }
        .mini-step {
            display: grid;
            align-content: center;
            min-height: 118px;
            box-sizing: border-box;
            padding: 24px 28px;
            border: 0;
            color: inherit;
            text-decoration: none;
        }
        .mini-step.current {
            background: var(--ledger-ink);
            color: var(--ledger-paper);
        }
        .mini-step.next {
            background: var(--ledger-signal);
            color: #101820;
        }
        .mini-step:hover,
        .mini-step:focus,
        .mini-step:focus-visible,
        .mini-step:hover *,
        .mini-step:focus *,
        .mini-step:focus-visible * {
            text-decoration: none;
        }
        .mini-step:hover { text-decoration: underline; text-underline-offset: 3px; }
        .mini-step:focus-visible {
            position: relative;
            z-index: 1;
            outline: 3px solid #fff;
            outline-offset: -6px;
        }
        .mini-label {
            color: var(--ledger-signal);
            font-size: 0.7rem;
            font-weight: 850;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .mini-step.next .mini-label { color: #101820; }
        .mini-step.next .mini-location,
        .mini-step.next .mini-countdown {
            color: #101820;
            opacity: 1;
        }
        .mini-title {
            margin-top: 5px;
            font-size: 1.05rem;
            font-weight: 800;
            overflow-wrap: anywhere;
        }
        .mini-location {
            color: inherit;
            opacity: 0.72;
            overflow-wrap: anywhere;
            font-size: 0.8rem;
            line-height: 1.42;
        }
        .mini-countdown {
            color: inherit;
            opacity: 0.72;
            font-size: 0.82rem;
            font-weight: 650;
            margin-top: 2px;
        }
        .mini-step[hidden] { display: none; }
        .timeline-now-button:disabled {
            cursor: not-allowed;
            opacity: 0.48;
        }
        .timeline-edit-panel {
            border: 1px solid var(--ledger-line);
            border-top: 0;
            border-radius: 0;
            background: #fff;
            margin: 0;
        }
        .timeline-edit-panel[hidden] {
            display: none;
        }
        .timeline-edit-panel .edit-form {
            padding: 14px 12px 12px;
        }
        .timeline-edit-panel .delete-segment-form {
            display: none;
        }
        .form-secondary-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .delete-item-link {
            appearance: none;
            background: transparent;
            border: 0;
            color: #9f1f1f;
            cursor: pointer;
            font: inherit;
            font-weight: 650;
            padding: 0;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .delete-item-link:hover,
        .delete-item-link:focus-visible {
            color: #7f1717;
        }
        .timeline-edit-panel .form-actions {
            justify-content: space-between;
            align-items: center;
        }
        .attachment-note {
            color: var(--wp-app-color-muted);
            font-size: 0.88rem;
            margin: 0;
            padding: 0 12px 12px;
        }
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
        .timeline-now-button,
        .add-item-button {
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            min-height: 32px;
            padding: 5px 9px;
            font-size: 0.88rem;
            line-height: 1.2;
        }
        .timeline-panel .add-item-button {
            background: var(--ledger-ink);
            color: var(--ledger-paper);
        }
        .timeline-panel .timeline-now-button {
            border-color: var(--ledger-line);
            color: var(--ledger-ink);
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
        .travel-journaling-zone,
        .settings-zone,
        .sharing-zone,
        .danger-zone {
            margin-top: 28px;
            border-top: 1px solid var(--wp-app-color-border);
            padding-top: 18px;
            color: var(--wp-app-color-muted);
        }
        .route-zone h2,
        .travel-journaling-zone h2,
        .settings-zone h2,
        .sharing-zone h2,
        .danger-zone h2 {
            color: var(--wp-app-color-text);
        }
        .route-zone details summary,
        .travel-journaling-zone details summary,
        .settings-zone details summary,
        .sharing-zone details summary,
        .danger-zone details summary {
            cursor: pointer;
            color: var(--wp-app-color-text);
            font-weight: 700;
        }
        .route-zone details summary h2,
        .travel-journaling-zone details summary h2,
        .settings-zone details summary h2,
        .sharing-zone details summary h2,
        .danger-zone details summary h2 {
            display: inline;
            margin: 0;
            font-size: 1.15rem;
        }
        .settings-form {
            display: grid;
            gap: 14px;
            margin-top: 14px;
        }
        .settings-help {
            margin: 12px 0 0;
            color: var(--wp-app-color-muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .setting-option {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin: 0;
            font-weight: 400;
        }
        .setting-option input {
            width: auto;
            margin-top: 4px;
        }
        .setting-option strong {
            display: block;
            color: var(--wp-app-color-text);
        }
        .setting-option span span {
            color: var(--wp-app-color-muted);
            font-size: 0.88rem;
        }
        .settings-form-actions {
            display: flex;
            justify-content: flex-end;
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
        @media (max-width: 820px) {
            .timeline-panel {
                grid-template-columns: minmax(0, 1fr);
            }
            .timeline-day-rail {
                position: sticky;
                z-index: 5;
                top: 0;
                min-height: 0;
                padding: 14px 16px;
                border-bottom: 1px solid rgba(247, 248, 245, 0.28);
            }
            .timeline-day-rail-title,
            .timeline-day-rail-summary { display: none; }
            .timeline-day-links {
                display: flex;
                gap: 5px;
                overflow-x: auto;
                padding: 2px;
                scrollbar-width: thin;
            }
            .timeline-day-link {
                flex: 0 0 auto;
                grid-template-columns: 28px auto;
                min-height: 44px;
                padding: 5px 9px;
            }
            .timeline-day-link small { display: none; }
            .timeline-ledger-content { padding: 22px 20px 38px; }
            .timeline-day { scroll-margin-top: 88px; }
            #wpadminbar ~ main .timeline-day-rail { top: 32px; }
            #wpadminbar ~ main .timeline-day { scroll-margin-top: 120px; }
        }
        @media (max-width: 680px) {
            main { padding-inline: 12px; }
            .timeline-panel { border: 0; }
            .timeline-item {
                grid-template-columns: 52px 38px minmax(0, 1fr);
                gap: 10px;
                padding: 12px 4px;
            }
            .timeline-state {
                grid-column: 3;
                justify-self: start;
                min-width: 0;
            }
            .timeline-symbol { width: 34px; height: 34px; }
            .summary-grid, .edit-form, .share-option { grid-template-columns: 1fr; }
            .url-preview { grid-template-columns: 1fr; }
            .url-preview-image { width: 100%; }
            .trip-title-header { align-items: flex-start; }
            .trip-title-form { grid-template-columns: 1fr; }
            .date-time-group { grid-template-columns: 1fr; }
            .mini-timeline { grid-template-columns: 1fr; }
            .mini-step { min-height: 104px; padding: 20px; }
            .timeline-header { flex-wrap: wrap; }
            .timeline-header-actions {
                margin-left: 0;
                justify-content: flex-start;
            }
            .time-marker span {
                left: 47px;
            }
            .time-marker::before { left: 54px; }
            .day-heading-row { grid-template-columns: minmax(0, 1fr) auto; gap: 8px; }
            .day-heading-copy { grid-template-columns: 54px minmax(0, 1fr); gap: 10px; }
            .day-number { font-size: 2.15rem; }
            .day-heading-controls { justify-content: flex-end; }
            .lodging-checker-night,
            .lodging-checker-night-covered { grid-template-columns: 1fr; }
            .lodging-checker-actions button { width: 100%; }
            .demo-controls label { min-width: 100%; }
            .offline-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 782px) {
            #wpadminbar ~ main .timeline-day-rail { top: 46px; }
            #wpadminbar ~ main .timeline-day { scroll-margin-top: 134px; }
        }
    </style>
</head>
<body>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_body_open(); ?>
    <?php endif; ?>

    <main>
        <?php if ( ! $is_readonly_timeline && $error ) : ?>
            <div class="notice error" role="alert"><?php echo esc_html( $traveler->get_error_notice_message( $error ) ); ?></div>
        <?php endif; ?>

        <?php if ( ! $trip_data ) : ?>
            <section class="panel">
                <h1><?php esc_html_e( 'Travel plan not found', 'traveler' ); ?></h1>
                <p class="empty"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'traveler' ); ?></p>
            </section>
        <?php else : ?>
            <header>
                <div class="trip-title-header">
                    <h1><span<?php echo App::mask_attr( 'title', (string) $trip_data['id'] ); ?>><?php echo esc_html( $trip_data['title'] ); ?></span></h1>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <button class="trip-title-edit-button" type="button" data-trip-title-edit aria-controls="trip-title-form" aria-expanded="false" title="<?php esc_attr_e( 'Edit travel plan title', 'traveler' ); ?>">
                            <span aria-hidden="true">✎</span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Edit travel plan title', 'traveler' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( ! $is_readonly_timeline ) : ?>
                    <form class="trip-title-form" id="trip-title-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync hidden>
                        <input type="hidden" name="action" value="traveler_update_trip">
                        <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                        <?php wp_nonce_field( 'traveler_update_trip_' . $trip_data['id'] ); ?>
                        <label for="trip_title">
                            <span class="screen-reader-text"><?php esc_html_e( 'Travel plan title', 'traveler' ); ?></span>
                            <input type="text" id="trip_title" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>" required>
                        </label>
                        <button type="submit"><?php esc_html_e( 'Save', 'traveler' ); ?></button>
                    </form>
                <?php endif; ?>
                <div class="meta">
                    <?php if ( '' !== $traveller_label ) : ?>
                        <span<?php echo App::mask_attr( 'person', (string) ( $trip_data['owner_id'] ?? '' ) ); ?>><?php echo esc_html( $traveller_label ); ?></span>
                    <?php endif; ?>
                    <?php foreach ( $traveler->get_trip_summary_parts( $trip_data, null, ! $is_static_download ) as $summary_part ) : ?>
                        <span><?php echo esc_html( $summary_part ); ?></span>
                    <?php endforeach; ?>
                    <span><?php echo esc_html( sprintf( _n( '%d item', '%d items', count( $segments ), 'traveler' ), count( $segments ) ) ); ?></span>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <?php if ( ! empty( $lodging_required_nights ) && empty( $lodging_missing_ranges ) ) : ?>
                            <button class="lodging-checker covered" type="button" data-lodging-checker-toggle aria-controls="lodging-checker-box" aria-expanded="false">
                                <span class="lodging-checker-icon" aria-hidden="true">✓</span>
                                <span><?php esc_html_e( 'Lodging covered', 'traveler' ); ?></span>
                            </button>
                        <?php elseif ( ! empty( $lodging_missing_ranges ) ) : ?>
                            <button class="lodging-checker" type="button" data-lodging-checker-toggle aria-controls="lodging-checker-box" aria-expanded="false">
                                <span class="lodging-checker-icon" aria-hidden="true">⚠</span>
                                <span>
                                    <?php
                                    printf(
                                        /* translators: %d: missing lodging night count. */
                                        esc_html( _n( '%d lodging night missing', '%d lodging nights missing', count( $missing_lodging_nights ), 'traveler' ) ),
                                        count( $missing_lodging_nights )
                                    );
                                    ?>
                                </span>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ( ! $is_static_download ) : ?>
                <div class="offline-status" data-offline-status role="status" aria-live="polite" hidden></div>
            <?php endif; ?>

            <?php
            $demo_control_id = 'trip-' . (string) $trip_data['id'];
            $demo_control_value = $demo_start_time;
            ?>
            <?php if ( $show_now_next_section && $is_trip_active && ! empty( $timeline_segments ) ) : ?>
                <section class="panel now-next-panel" aria-label="<?php esc_attr_e( 'Now and Next', 'traveler' ); ?>">
                    <div class="mini-timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>" data-demo-preview data-current-time-value="<?php echo esc_attr( $timeline_current_time_value ); ?>" data-current-time-captured="<?php echo esc_attr( $timeline_current_time_captured ); ?>">
                        <?php foreach ( $timeline_segments as $step ) : ?>
                            <?php
                            if ( empty( $step['date'] ) ) {
                                continue;
                            }

                            $step_timeline_kind = (string) ( $step['_timeline_kind'] ?? 'start' );
                            $step_anchor_suffix = in_array( $step_timeline_kind, [ 'checkout', 'return' ], true ) ? '-' . $step_timeline_kind : '';
                            $step_anchor = 'segment-' . (int) ( $step['_index'] ?? 0 ) . $step_anchor_suffix;
                            $step_date = (string) ( $step['date'] ?? '' );
                            $step_end_time = (string) ( $step['end_time'] ?? '' );
                            $step_end_date = (string) ( $step['end_date'] ?? '' );
                            $step_effective_end_date = '' !== $step_end_date ? $step_end_date : ( '' !== $step_end_time ? $step_date : '' );
                            $step_datetime = trim( (string) ( $step['date'] ?? '' ) . 'T' . ( (string) ( $step['time'] ?? '' ) ?: '00:00' ) );
                            $step_time_label = ( '' !== $step_effective_end_date && $step_effective_end_date === $step_date && '' !== $step_end_time )
                                ? $traveler->format_time_range_label( (string) ( $step['time'] ?? '' ), $step_end_time )
                                : (string) ( $step['time'] ?? '' );
                            $step_start_label = trim( $traveler->format_date_label( $step_date ) . ' ' . (string) ( $step['time'] ?? '' ) );
                            $step_end_label = '' !== $step_effective_end_date && $step_effective_end_date !== $step_date
                                ? trim( $traveler->format_date_label( $step_effective_end_date ) . ' ' . $step_end_time )
                                : '';
                            $step_show_location = 'checkout' !== $step_timeline_kind && ( $show_private_share_details || $is_transport_segment( $step ) );
                            $step_location = $step_show_location ? (string) ( $step['location'] ?? '' ) : '';
                            $step_end_location = $step_show_location ? (string) ( $step['end_location'] ?? '' ) : '';
                            $step_title = (string) ( $step['title'] ?? '' );
                            if ( 'checkout' === $step_timeline_kind ) {
                                $step_title = '' !== $step_title
                                    ? sprintf( __( 'Check out: %s', 'traveler' ), $step_title )
                                    : __( 'Check out', 'traveler' );
                            } elseif ( 'return' === $step_timeline_kind ) {
                                $step_title = '' !== $step_title
                                    ? sprintf( __( 'Return car: %s', 'traveler' ), $step_title )
                                    : __( 'Return car', 'traveler' );
                            }
                            ?>
                            <span hidden data-preview-item data-url="<?php echo esc_url( '#' . $step_anchor ); ?>" data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-timeline-kind="<?php echo esc_attr( $step_timeline_kind ); ?>" data-type="<?php echo esc_attr( (string) ( $step['type'] ?? '' ) ); ?>" data-date="<?php echo esc_attr( $step_date ); ?>" data-time-label="<?php echo esc_attr( $step_time_label ); ?>" data-date-time-label="<?php echo esc_attr( $step_start_label ); ?>" data-end-date="<?php echo esc_attr( $step_effective_end_date ); ?>" data-end-time="<?php echo esc_attr( $step_end_time ); ?>" data-end-label="<?php echo esc_attr( $step_end_label ); ?>" data-location="<?php echo esc_attr( $step_location ); ?>" data-end-location="<?php echo esc_attr( $step_end_location ); ?>" data-title="<?php echo esc_attr( $step_title ); ?>"></span>
                        <?php endforeach; ?>
                        <?php foreach ( [ 'current' => __( 'Now', 'traveler' ), 'next' => __( 'Next', 'traveler' ) ] as $key => $label ) : ?>
                            <a class="mini-step <?php echo esc_attr( $key ); ?>" href="#" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-slot-label="<?php echo esc_attr( $label ); ?>" data-ended-label="<?php esc_attr_e( 'Last', 'traveler' ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'traveler' ); ?>">
                                <div class="mini-label" data-preview-label><?php echo esc_html( $label ); ?></div>
                                <div class="mini-title" data-preview-title<?php echo App::mask_attr( 'title' ); ?>><?php esc_html_e( 'No item', 'traveler' ); ?></div>
                                <div class="mini-countdown" data-preview-countdown></div>
                                <div class="mini-location" data-preview-meta<?php echo App::mask_attr( 'text' ); ?>></div>
                                <div class="mini-location" data-preview-location<?php echo App::mask_attr( 'place' ); ?>></div>
                                <div class="mini-location" data-preview-end<?php echo App::mask_attr( 'text' ); ?>></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel timeline-panel" aria-labelledby="timeline-heading" data-ai-assistant-important>
                <aside class="timeline-day-rail" aria-label="<?php esc_attr_e( 'Timeline days', 'traveler' ); ?>">
                    <strong class="timeline-day-rail-title"><?php esc_html_e( 'Trip timeline', 'traveler' ); ?></strong>
                    <p class="timeline-day-rail-summary">
                        <?php
                        printf(
                            /* translators: 1: formatted itinerary day count, 2: formatted trip item count. */
                            esc_html__( '%1$s · %2$s', 'traveler' ),
                            esc_html( sprintf( _n( '%d day', '%d days', count( $segments_by_day ), 'traveler' ), count( $segments_by_day ) ) ),
                            esc_html( sprintf( _n( '%d item', '%d items', count( $segments ), 'traveler' ), count( $segments ) ) )
                        );
                        ?>
                    </p>
                    <?php if ( ! empty( $segments_by_day ) ) : ?>
                        <nav class="timeline-day-links" aria-label="<?php esc_attr_e( 'Jump to day', 'traveler' ); ?>">
                            <?php foreach ( array_keys( $segments_by_day ) as $day_index => $day_link_date ) : ?>
                                <?php $day_link_timestamp = strtotime( $day_link_date . ' 12:00:00' ); ?>
                                <a
                                    class="timeline-day-link<?php echo 0 === $day_index ? ' is-active' : ''; ?><?php echo $day_link_date === $today ? ' is-current' : ''; ?>"
                                    href="#timeline-day-<?php echo esc_attr( $day_link_date ); ?>"
                                    data-timeline-day-link="<?php echo esc_attr( $day_link_date ); ?>"
                                    <?php echo $day_link_date === $today ? 'aria-current="date"' : ''; ?>
                                >
                                    <strong><?php echo esc_html( $day_link_timestamp ? date_i18n( 'd', $day_link_timestamp ) : $day_link_date ); ?></strong>
                                    <span><?php echo esc_html( $day_link_timestamp ? date_i18n( 'D', $day_link_timestamp ) : '' ); ?></span>
                                    <small><?php echo esc_html( $day_link_timestamp ? date_i18n( 'M', $day_link_timestamp ) : '' ); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </aside>
                <div class="timeline-ledger-content">
                <div class="timeline-header">
                    <div class="timeline-header-copy">
                        <h2 id="timeline-heading"><?php esc_html_e( 'Timeline', 'traveler' ); ?></h2>
                        <p><?php esc_html_e( 'Times, places, and travel details in one working view.', 'traveler' ); ?></p>
                    </div>
                    <div class="timeline-header-actions">
                        <?php if ( $is_trip_active ) : ?>
                            <button class="ghost-button timeline-now-button" type="button" data-timeline-now aria-controls="timeline" aria-label="<?php esc_attr_e( 'Jump to current time', 'traveler' ); ?>" title="<?php esc_attr_e( 'Jump to current time', 'traveler' ); ?>" disabled>
                                <?php esc_html_e( 'Now', 'traveler' ); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ( ! $is_readonly_timeline ) : ?>
                            <button class="add-item-button" type="button" data-add-item-toggle aria-controls="add-item-form" aria-expanded="<?php echo ! empty( $quick_plan_segment ) ? 'true' : 'false'; ?>">
                                <?php esc_html_e( '+ Add Item', 'traveler' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                if ( $show_timeline_demo_controls ) {
                    require __DIR__ . '/partials/demo-controls.php';
                }
                ?>

                <?php if ( ! $is_readonly_timeline && ! empty( $missing_lodging_night_details ) ) : ?>
                    <div class="lodging-checker-box" id="lodging-checker-box" data-lodging-checker-box hidden>
                        <div class="lodging-checker-box-header">
                            <span>
                                <strong><?php esc_html_e( 'Lodging missing', 'traveler' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %d: missing lodging night count. */
                                    esc_html( _n( 'Review %d night without lodging.', 'Review %d nights without lodging.', count( $missing_lodging_nights ), 'traveler' ) ),
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
                                        <?php echo esc_html( $traveler->format_date_label( (string) $missing_lodging_night['date'], false ) ); ?>
                                        <span aria-hidden="true">→</span>
                                        <?php echo esc_html( $traveler->format_date_label( (string) $missing_lodging_night['end_date'] ) ); ?>
                                    </span>
                                </label>
                                <label>
                                    <span class="screen-reader-text"><?php esc_html_e( 'Location', 'traveler' ); ?></span>
                                    <input
                                        type="text"
                                        data-lodging-night-location
                                        value="<?php echo esc_attr( (string) $missing_lodging_night['location'] ); ?>"
                                        placeholder="<?php esc_attr_e( 'Location', 'traveler' ); ?>"
                                    >
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if ( empty( $quick_plan_segment ) ) : ?>
                            <div class="lodging-checker-actions">
                                <button type="button" data-lodging-prefill><?php esc_html_e( 'Add selected lodging', 'traveler' ); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ( ! $is_readonly_timeline && ! empty( $covered_lodging_night_details ) ) : ?>
                    <div class="lodging-checker-box covered" id="lodging-checker-box" data-lodging-checker-box hidden>
                        <div class="lodging-checker-box-header">
                            <span>
                                <strong><?php esc_html_e( 'Lodging covered', 'traveler' ); ?></strong>
                                <?php
                                printf(
                                    /* translators: %d: covered lodging night count. */
                                    esc_html( _n( 'Confirmed for %d night.', 'Confirmed for %d nights.', count( $covered_lodging_night_details ), 'traveler' ) ),
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
                                : ( $segment_type_labels[ $covered_item_type ] ?? __( 'Itinerary item', 'traveler' ) );
                            ?>
                            <div class="lodging-checker-night lodging-checker-night-covered">
                                <span class="lodging-checker-night-status">
                                    <span class="lodging-checker-icon" aria-hidden="true">✓</span>
                                    <span>
                                        <?php echo esc_html( $traveler->format_date_label( (string) $covered_lodging_night['date'], false ) ); ?>
                                        <span aria-hidden="true">→</span>
                                        <?php echo esc_html( $traveler->format_date_label( (string) $covered_lodging_night['end_date'] ) ); ?>
                                    </span>
                                </span>
                                <span class="lodging-checker-brief">
                                    <span<?php echo App::mask_attr( 'title', (string) ( $covered_lodging_night['item_id'] ?? 0 ) . '-item' ); ?>><?php echo esc_html( $covered_item_label ); ?></span>
                                    <?php if ( isset( $segment_type_labels[ $covered_item_type ] ) ) : ?>
                                        · <?php echo esc_html( $segment_type_labels[ $covered_item_type ] ); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="lodging-checker-brief"<?php echo App::mask_attr( 'place', (string) ( $covered_lodging_night['item_id'] ?? 0 ) . '-location' ); ?>><?php echo esc_html( (string) ( $covered_lodging_night['location'] ?? '' ) ); ?></span>
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
                                'wp-ai-client' => __( 'AI extraction', 'traveler' ),
                                'quick-plan'   => __( 'quick planner fallback', 'traveler' ),
                                'fallback'     => __( 'basic parser fallback', 'traveler' ),
                                'ics'          => __( 'calendar parser', 'traveler' ),
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
                            <summary><?php esc_html_e( 'Import or Add from Text', 'traveler' ); ?></summary>
                            <form class="trip-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="traveler_import">
                                <input type="hidden" name="import_trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <?php wp_nonce_field( 'traveler_import' ); ?>
                                <label for="trip_import_text">
                                    <?php
                                    printf(
                                        /* translators: %s: trip title. */
                                        esc_html__( 'Paste confirmation, file text, or a typed entry for %s', 'traveler' ),
                                        esc_html( $trip_data['title'] )
                                    );
                                    ?>
                                </label>
                                <textarea id="trip_import_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Example: Dinner in Hamburg on August 2 at 7pm...', 'traveler' ); ?>"></textarea>
                                <p class="hint"><?php echo esc_html( $has_ai ? __( 'AI extraction can turn plain text into an entry for review; confirmations still work too.', 'traveler' ) : __( 'Uses quick parsing or a basic parser.', 'traveler' ) ); ?></p>
                                <div class="form-actions">
                                    <button type="submit"><?php esc_html_e( 'Review Import', 'traveler' ); ?></button>
                                </div>
                            </form>
                        </details>

                        <form class="edit-form add-item-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo empty( $quick_plan_segment ) ? ' data-offline-sync' : ''; ?>>
                            <input type="hidden" name="action" value="<?php echo ! empty( $quick_plan_segment ) ? 'traveler_import' : 'traveler_add_segment'; ?>">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <?php if ( ! empty( $quick_plan_segment ) ) : ?>
                                <input type="hidden" name="import_trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <input type="hidden" name="quick_plan_draft" value="<?php echo esc_attr( $quick_plan_draft_key ); ?>">
                                <input type="hidden" name="quick_plan_target" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <?php wp_nonce_field( 'traveler_import' ); ?>
                                <p class="empty field-wide">
                                    <?php
                                    printf(
                                        /* translators: %s: parser source label. */
                                        esc_html__( 'Prefilled from text. Review the fields before adding this entry. Parsed with: %s.', 'traveler' ),
                                        esc_html( $quick_plan_parser_label )
                                    );
                                    ?>
                                    <?php if ( '' !== $quick_plan_parser_error_code || '' !== $quick_plan_parser_error_message ) : ?>
                                        <?php
                                        printf(
                                            /* translators: 1: parser error code, 2: parser error message. */
                                            esc_html__( ' Parser error: %1$s %2$s', 'traveler' ),
                                            esc_html( $quick_plan_parser_error_code ),
                                            esc_html( $quick_plan_parser_error_message )
                                        );
                                        ?>
                                    <?php endif; ?>
                                </p>
                            <?php else : ?>
                                <?php wp_nonce_field( 'traveler_add_segment_' . $trip_data['id'] ); ?>
                            <?php endif; ?>
                            <label class="field-wide">
                                <?php esc_html_e( 'Title', 'traveler' ); ?>
                                <input name="segment_title" value="<?php echo esc_attr( (string) ( $quick_plan_segment['title'] ?? '' ) ); ?>">
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'Type', 'traveler' ); ?>
                                <select name="segment_type">
                                    <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $quick_plan_segment['type'] ?? 'activity', $type ); ?>><?php echo esc_html( $segment_type_labels[ $type ] ?? ucfirst( $type ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'URL', 'traveler' ); ?>
                                <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $quick_plan_segment['url'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Location', 'traveler' ); ?>
                                <input name="segment_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['location'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'End Location', 'traveler' ); ?>
                                <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_location'] ?? '' ) ); ?>">
                            </label>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'Start Date', 'traveler' ); ?>
                                    <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Time', 'traveler' ); ?>
                                    <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'End Date', 'traveler' ); ?>
                                    <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Time', 'traveler' ); ?>
                                    <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <label class="field-wide">
                                <?php esc_html_e( 'Details', 'traveler' ); ?>
                                <textarea name="segment_details"><?php echo esc_textarea( (string) ( $quick_plan_segment['details'] ?? '' ) ); ?></textarea>
                            </label>
                            <div class="form-actions">
                                <button type="submit"><?php echo esc_html( ! empty( $quick_plan_segment ) ? __( 'Add to This Trip', 'traveler' ) : __( 'Add Item', 'traveler' ) ); ?></button>
                            </div>
                        </form>
                    </div>
                    <?php
                    $segment_form_template_segment = [
                        'type'              => 'other',
                        'title'             => '',
                        'date'              => '',
                        'end_date'          => '',
                        'time'              => '',
                        'end_time'          => '',
                        'location'          => '',
                        'end_location'      => '',
                        'url'               => '',
                        'url_preview'       => [],
                        'url_preview_debug' => [],
                        'details'           => '',
                    ];
                    $segment_form_template_index = 0;
                    ?>
                    <script type="application/json" id="traveler-trip-data"><?php echo wp_json_encode( $editable_trip_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
                    <template id="segment-edit-template">
                        <?php
                        $segment = $segment_form_template_segment;
                        $index = $segment_form_template_index;
                        require __DIR__ . '/partials/segment-form.php';
                        ?>
                        <p class="attachment-note"><?php esc_html_e( 'Attachments can be opened from the timeline. Uploading or deleting attachments requires an online connection.', 'traveler' ); ?></p>
                    </template>
                <?php endif; ?>

                <?php if ( empty( $segments_by_day ) ) : ?>
                    <p class="empty"><?php esc_html_e( 'No timeline items were found.', 'traveler' ); ?></p>
                <?php else : ?>
                    <div class="timeline" id="timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>" data-state-current="<?php esc_attr_e( 'Current', 'traveler' ); ?>" data-state-past="<?php esc_attr_e( 'Passed', 'traveler' ); ?>" data-state-planned="<?php esc_attr_e( 'Planned', 'traveler' ); ?>" data-state-generated="<?php esc_attr_e( 'Generated', 'traveler' ); ?>"<?php echo $is_readonly_timeline ? ' data-readonly-timeline="1"' : ''; ?><?php echo $is_trip_active ? ' data-current-time="1" data-current-time-value="' . esc_attr( $timeline_current_time_value ) . '" data-current-time-captured="' . esc_attr( $timeline_current_time_captured ) . '"' : ''; ?>>
                        <?php if ( $show_timeline_time_marker ) : ?>
                            <div class="time-marker"><span class="time-marker-label"></span></div>
                        <?php endif; ?>
                        <?php foreach ( $segments_by_day as $day => $day_segments ) : ?>
                            <?php
                            $journal_entry = $journal_entries_by_day[ $day ] ?? [];
                            $journal_exists = ! empty( $journal_entry );
                            $day_timestamp = strtotime( $day . ' 12:00:00' );
                            $day_heading_id = 'timeline-day-heading-' . $day;
                            $day_content_id = 'timeline-day-content-' . $day;
                            ?>
                            <section class="timeline-day<?php echo empty( $day_segments ) ? ' empty' : ''; ?>" id="timeline-day-<?php echo esc_attr( $day ); ?>" data-date="<?php echo esc_attr( $day ); ?>" aria-labelledby="<?php echo esc_attr( $day_heading_id ); ?>">
                                <div class="day-heading-row">
                                    <div class="day-heading-copy">
                                        <span class="day-number" aria-hidden="true"><?php echo esc_html( $day_timestamp ? date_i18n( 'd', $day_timestamp ) : $day ); ?></span>
                                        <div class="day-heading-meta">
                                            <h3 class="day-heading" id="<?php echo esc_attr( $day_heading_id ); ?>"><?php echo esc_html( $traveler->format_date_label( $day ) ); ?></h3>
                                            <span class="day-item-count">
                                                <?php echo esc_html( sprintf( _n( '%d timeline item', '%d timeline items', count( $day_segments ), 'traveler' ), count( $day_segments ) ) ); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="day-heading-controls">
                                        <?php if ( ! $is_readonly_timeline && $journal_enabled ) : ?>
                                            <div class="day-journal-actions">
                                            <form class="day-journal-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                <input type="hidden" name="action" value="traveler_open_journal_entry">
                                                <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                                <input type="hidden" name="journal_date" value="<?php echo esc_attr( $day ); ?>">
                                                <?php wp_nonce_field( 'traveler_open_journal_entry_' . $trip_data['id'] ); ?>
                                                <button class="day-journal-button" type="submit">
                                                    <?php echo esc_html( $journal_exists ? __( 'Edit Journal', 'traveler' ) : __( 'Start Journal', 'traveler' ) ); ?>
                                                </button>
                                            </form>
                                            <?php if ( $journal_exists ) : ?>
                                                <form class="day-journal-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                    <input type="hidden" name="action" value="traveler_prepare_journal_post">
                                                    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                                    <input type="hidden" name="journal_id" value="<?php echo esc_attr( (string) ( $journal_entry['id'] ?? 0 ) ); ?>">
                                                    <?php wp_nonce_field( 'traveler_prepare_journal_post_' . $trip_data['id'] . '_' . (int) ( $journal_entry['id'] ?? 0 ) ); ?>
                                                    <button class="day-journal-button" type="submit">
                                                        <?php echo esc_html( ! empty( $journal_entry['post_id'] ) ? __( 'Update Linked Post', 'traveler' ) : __( 'Prepare for Publishing', 'traveler' ) ); ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <button class="day-collapse-button" type="button" data-timeline-day-toggle aria-controls="<?php echo esc_attr( $day_content_id ); ?>" aria-expanded="true" data-expand-label="<?php esc_attr_e( 'Expand', 'traveler' ); ?>" data-collapse-label="<?php esc_attr_e( 'Collapse', 'traveler' ); ?>">
                                            <span data-timeline-day-toggle-label><?php esc_html_e( 'Collapse', 'traveler' ); ?></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="timeline-day-content" id="<?php echo esc_attr( $day_content_id ); ?>">
                                <?php if ( empty( $day_segments ) ) : ?>
                                    <p class="timeline-day-empty"><?php esc_html_e( 'Nothing scheduled. Add an item when the plan takes shape.', 'traveler' ); ?></p>
                                <?php endif; ?>
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
                                        $type_label = __( 'Check out', 'traveler' );
                                        $timeline_type_code = 'OUT';
                                    } elseif ( 'return' === $timeline_kind ) {
                                        $type_label = __( 'Return car', 'traveler' );
                                        $timeline_type_code = 'IN';
                                    } elseif ( 'car' === ( $segment['type'] ?? '' ) ) {
                                        $type_label = __( 'Rental car', 'traveler' );
                                        $timeline_type_code = $timeline_type_codes['car'];
                                    } else {
                                        $type_label = $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'traveler' ) );
                                        $timeline_type_code = $timeline_type_codes[ $segment['type'] ?? 'other' ] ?? $timeline_type_codes['other'];
                                    }
                                    $initial_state = $day < $today ? __( 'Passed', 'traveler' ) : ( $is_end_timeline_entry ? __( 'Generated', 'traveler' ) : __( 'Planned', 'traveler' ) );
                                    ?>
                                    <?php $url_preview = isset( $segment['url_preview'] ) && is_array( $segment['url_preview'] ) ? $segment['url_preview'] : []; ?>
                                    <?php $attachments = $show_attachments && isset( $segment['attachments'] ) && is_array( $segment['attachments'] ) ? $segment['attachments'] : []; ?>
                                    <?php $has_url_preview = $show_url_preview && ! empty( $url_preview ) && ( ! empty( $url_preview['title'] ) || ! empty( $url_preview['description'] ) || ! empty( $url_preview['image'] ) ); ?>
                                    <div class="timeline-item-wrap" id="<?php echo esc_attr( $segment_anchor ); ?>">
                                        <div class="timeline-item<?php echo $day < $today ? ' past' : ''; ?>" data-inline-edit-view data-date="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>" data-time="<?php echo esc_attr( (string) ( $segment['time'] ?? '' ) ); ?>" data-end-date="<?php echo esc_attr( (string) ( $segment['end_date'] ?? '' ) ); ?>" data-end-time="<?php echo esc_attr( (string) ( $segment['end_time'] ?? '' ) ); ?>" data-datetime="<?php echo esc_attr( $segment_datetime ); ?>" data-generated="<?php echo $is_end_timeline_entry ? '1' : '0'; ?>">
                                            <div class="timeline-meta">
                                                <time class="time" datetime="<?php echo esc_attr( $segment_datetime ); ?>"><?php echo esc_html( $segment['time'] ?: '—' ); ?></time>
                                            </div>
                                            <span class="timeline-symbol" aria-hidden="true"><?php echo esc_html( $timeline_type_code ); ?></span>
                                            <div class="timeline-event">
                                                <span class="screen-reader-text"><?php echo esc_html( $type_label ); ?>: </span>
                                                <div class="timeline-title-row title">
                                                    <?php if ( $is_readonly_timeline ) : ?>
                                                        <span<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                    <?php elseif ( ! $is_end_timeline_entry ) : ?>
                                                        <button class="timeline-title-button" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                            <span<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                        </button>
                                                    <?php else : ?>
                                                        <span<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $show_url_preview && ! $has_url_preview && ! empty( $segment['url'] ) ) : ?>
                                                        <a class="timeline-url-link" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open item URL', 'traveler' ); ?>">
                                                            <span aria-hidden="true">↗</span>
                                                            <span class="screen-reader-text"><?php esc_html_e( 'Open item URL', 'traveler' ); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ( '' !== $segment_end_date && $segment_end_date !== $segment_start_date ) : ?>
                                                    <div class="detail"><?php echo esc_html( $traveler->get_segment_date_range_label( $segment ) ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ! empty( $segment['location'] ) ) : ?>
                                                    <?php $location = (string) $segment['location']; ?>
                                                    <div class="detail">
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span aria-hidden="true">&#x1F4CD;</span>
                                                            <span<?php echo App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ); ?>><?php echo esc_html( $location ); ?></span>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                    <?php $end_location = (string) $segment['end_location']; ?>
                                                    <div class="detail">
                                                        <?php esc_html_e( 'To:', 'traveler' ); ?>
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span aria-hidden="true">&#x1F4CD;</span>
                                                            <span<?php echo App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ); ?>><?php echo esc_html( $end_location ); ?></span>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_private_share_details && ! empty( $segment['details'] ) ) : ?>
                                                    <div class="detail timeline-note"<?php echo App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-details' ); ?>><?php echo esc_html( $segment['details'] ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $attachments ) ) : ?>
                                                    <div class="attachment-links" aria-label="<?php esc_attr_e( 'Attachments', 'traveler' ); ?>">
                                                        <?php foreach ( $attachments as $attachment ) : ?>
                                                            <?php
                                                            if ( empty( $attachment['url'] ) ) {
                                                                continue;
                                                            }
                                                            $attachment_label = (string) ( ( $attachment['title'] ?? '' ) ?: ( $attachment['filename'] ?? __( 'Attachment', 'traveler' ) ) );
                                                            ?>
                                                            <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( sprintf( __( 'Download %s', 'traveler' ), $attachment_label ) ); ?>" data-offline-cache-url>
                                                                <span aria-hidden="true">↓</span>
                                                                <span<?php echo App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-attachment-' . (string) ( $attachment['id'] ?? md5( $attachment_label ) ) ); ?>><?php echo esc_html( $attachment_label ); ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $has_url_preview ) : ?>
                                                    <?php $has_url_preview_image = ! empty( $url_preview['image'] ); ?>
                                                    <a class="url-preview<?php echo $has_url_preview_image ? '' : ' no-image'; ?>" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php if ( $has_url_preview_image ) : ?>
                                                            <img class="url-preview-image"<?php echo App::mask_attr( 'image', (string) ( $segment['id'] ?? $index ) . '-preview' ); ?> src="<?php echo esc_url( (string) $url_preview['image'] ); ?>" alt="" loading="lazy">
                                                        <?php endif; ?>
                                                        <div class="url-preview-text">
                                                            <?php if ( ! empty( $url_preview['site_name'] ) ) : ?>
                                                                <div class="url-preview-meta"><?php echo esc_html( (string) $url_preview['site_name'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['title'] ) ) : ?>
                                                                <div class="url-preview-title"<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-preview' ); ?>><?php echo esc_html( (string) $url_preview['title'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['description'] ) ) : ?>
                                                                <div class="url-preview-description"<?php echo App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-preview-description' ); ?>><?php echo esc_html( (string) $url_preview['description'] ); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <span class="timeline-state<?php echo $is_end_timeline_entry && $day >= $today ? ' generated' : ''; ?>" data-timeline-state><?php echo esc_html( $initial_state ); ?></span>
                                        </div>
                                        <?php if ( ! $is_readonly_timeline && ! $is_end_timeline_entry ) : ?>
                                            <div class="timeline-edit-panel" id="<?php echo esc_attr( 'edit-segment-' . $index ); ?>" data-inline-edit-panel hidden>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </section>

            <?php if ( ! empty( $unscheduled_segments ) ) : ?>
                <section class="panel" aria-labelledby="items-heading">
                    <h2 id="items-heading"><?php esc_html_e( 'Unscheduled Items', 'traveler' ); ?></h2>
                    <div>
                        <?php foreach ( $unscheduled_segments as $segment ) : ?>
                            <?php $index = (int) $segment['_index']; ?>
                            <?php $show_location = $show_private_share_details || $is_transport_segment( $segment ); ?>
                            <?php $attachments = $show_private_share_details && isset( $segment['attachments'] ) && is_array( $segment['attachments'] ) ? $segment['attachments'] : []; ?>
                            <div class="item unscheduled-link" id="segment-<?php echo esc_attr( (string) $index ); ?>" data-inline-edit-view>
                                    <div class="summary-grid">
                                        <span class="time"><?php echo esc_html( trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) ) ); ?></span>
                                        <span>
                                            <span class="type"><?php echo esc_html( $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'traveler' ) ) ); ?></span><br>
                                            <?php if ( $is_readonly_timeline ) : ?>
                                                <span class="title"<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                            <?php else : ?>
                                                <button class="timeline-title-button title" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                    <span<?php echo App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $segment['end_date'] ) ) : ?>
                                                <br><span class="detail"><?php echo esc_html( $traveler->get_segment_date_range_label( $segment ) ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( $show_location && ! empty( $segment['location'] ) ) : ?>
                                                <?php $location = (string) $segment['location']; ?>
                                                <br><span class="detail">
                                                    <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <span aria-hidden="true">&#x1F4CD;</span>
                                                        <span<?php echo App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ); ?>><?php echo esc_html( $location ); ?></span>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                <?php $end_location = (string) $segment['end_location']; ?>
                                                <br><span class="detail">
                                                    <?php esc_html_e( 'To:', 'traveler' ); ?>
                                                    <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <span aria-hidden="true">&#x1F4CD;</span>
                                                        <span<?php echo App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ); ?>><?php echo esc_html( $end_location ); ?></span>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $attachments ) ) : ?>
                                                <div class="attachment-links" aria-label="<?php esc_attr_e( 'Attachments', 'traveler' ); ?>">
                                                    <?php foreach ( $attachments as $attachment ) : ?>
                                                        <?php
                                                        if ( empty( $attachment['url'] ) ) {
                                                            continue;
                                                        }
                                                        $attachment_label = (string) ( ( $attachment['title'] ?? '' ) ?: ( $attachment['filename'] ?? __( 'Attachment', 'traveler' ) ) );
                                                        ?>
                                                        <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( sprintf( __( 'Download %s', 'traveler' ), $attachment_label ) ); ?>" data-offline-cache-url>
                                                            <span aria-hidden="true">↓</span>
                                                            <span<?php echo App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-attachment-' . (string) ( $attachment['id'] ?? md5( $attachment_label ) ) ); ?>><?php echo esc_html( $attachment_label ); ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ( ! $is_readonly_timeline ) : ?>
                                            <button class="ghost-button" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                <?php esc_html_e( 'Edit', 'traveler' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                            </div>
                            <?php if ( ! $is_readonly_timeline ) : ?>
                                <div class="timeline-edit-panel" id="<?php echo esc_attr( 'edit-segment-' . $index ); ?>" data-inline-edit-panel hidden>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $show_private_share_details && ( ! empty( $trip_route_links ) || '' !== $trip_direct_map_url ) ) : ?>
                <section class="route-zone" aria-labelledby="route-maps-heading">
                    <details>
                        <summary><h2 id="route-maps-heading"><?php esc_html_e( 'Route Maps', 'traveler' ); ?></h2></summary>
                        <div class="trip-actions" aria-label="<?php esc_attr_e( 'Route links', 'traveler' ); ?>">
                            <?php if ( ! empty( $trip_route_links['google'] ) ) : ?>
                                <a class="ghost-button" href="<?php echo esc_url( (string) $trip_route_links['google'] ); ?>" target="_blank" rel="noopener noreferrer">
                                    <span aria-hidden="true">&#x1F5FA;</span>
                                    <?php esc_html_e( 'Google Maps', 'traveler' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( '' !== $trip_direct_map_url ) : ?>
                                <a class="ghost-button" href="<?php echo esc_url( $trip_direct_map_url ); ?>">
                                    <span aria-hidden="true">&#x1F5FA;</span>
                                    <?php esc_html_e( 'OpenStreetMap', 'traveler' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="sharing-zone" aria-labelledby="sharing-heading" data-share-control data-trip-id="<?php echo esc_attr( (string) $trip_data['id'] ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'traveler_share_link_' . $trip_data['id'] ) ); ?>" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                    <details>
                        <summary><h2 id="sharing-heading"><?php esc_html_e( 'Sharing', 'traveler' ); ?></h2></summary>
                        <div class="share-link">
                            <div class="share-option">
                                <span>
                                    <strong><?php esc_html_e( 'Fellow travellers', 'traveler' ); ?></strong><br>
                                    <span class="empty"><?php esc_html_e( 'Includes addresses and attachments.', 'traveler' ); ?></span>
                                </span>
                                <span class="share-actions">
                                    <a class="ghost-button" href="<?php echo esc_url( $traveler->get_trip_html_download_url( (int) $trip_data['id'], 'fellow' ) ); ?>">
                                        <?php esc_html_e( 'HTML', 'traveler' ); ?>
                                    </a>
                                    <?php if ( ! $traveler->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="timeline" data-share-mode="fellow" data-share-url="<?php echo esc_attr( $fellow_share_url ); ?>"><?php esc_html_e( 'URL', 'traveler' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="calendar" data-share-mode="fellow" data-share-url="<?php echo esc_attr( $fellow_calendar_url ); ?>"><?php esc_html_e( 'ICS', 'traveler' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="fellow" <?php echo '' === $fellow_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Stop sharing', 'traveler' ); ?></button>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="share-option">
                                <span>
                                    <strong><?php esc_html_e( 'Others', 'traveler' ); ?></strong><br>
                                    <span class="empty"><?php esc_html_e( 'Shows transport start and end locations; hides other addresses and attachments.', 'traveler' ); ?></span>
                                </span>
                                <span class="share-actions">
                                    <a class="ghost-button" href="<?php echo esc_url( $traveler->get_trip_html_download_url( (int) $trip_data['id'], 'public' ) ); ?>">
                                        <?php esc_html_e( 'HTML', 'traveler' ); ?>
                                    </a>
                                    <?php if ( ! $traveler->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="timeline" data-share-mode="public" data-share-url="<?php echo esc_attr( $public_share_url ); ?>"><?php esc_html_e( 'URL', 'traveler' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="calendar" data-share-mode="public" data-share-url="<?php echo esc_attr( $public_calendar_url ); ?>"><?php esc_html_e( 'ICS', 'traveler' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="public" <?php echo '' === $public_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Stop sharing', 'traveler' ); ?></button>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php if ( ! $traveler->is_playground() ) : ?>
                            <p class="empty" data-share-status aria-live="polite"></p>
                        <?php endif; ?>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="settings-zone" aria-labelledby="settings-heading">
                    <details>
                        <summary><h2 id="settings-heading"><?php esc_html_e( 'Settings', 'traveler' ); ?></h2></summary>
                        <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync>
                            <input type="hidden" name="action" value="traveler_update_trip">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                            <input type="hidden" name="trip_show_now_next_present" value="1">
                            <?php wp_nonce_field( 'traveler_update_trip_' . $trip_data['id'] ); ?>
                            <label class="setting-option">
                                <input type="checkbox" name="trip_show_now_next" value="1" <?php checked( $show_now_next_section ); ?>>
                                <span>
                                    <strong><?php esc_html_e( 'Show Now and Next', 'traveler' ); ?></strong>
                                    <span><?php esc_html_e( 'Display the current and next itinerary items above the timeline while this trip is active.', 'traveler' ); ?></span>
                                </span>
                            </label>
                            <div class="settings-form-actions">
                                <button type="submit"><?php esc_html_e( 'Save Settings', 'traveler' ); ?></button>
                            </div>
                        </form>
                        <?php if ( $can_manage_trip_editors ) : ?>
                            <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="traveler_update_trip">
                                <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                                <input type="hidden" name="trip_editors_present" value="1">
                                <?php wp_nonce_field( 'traveler_update_trip_' . $trip_data['id'] ); ?>
                                <p class="settings-help"><?php esc_html_e( 'Choose WordPress users who can modify this travel plan.', 'traveler' ); ?></p>
                                <?php if ( empty( $trip_editor_candidates ) ) : ?>
                                    <p class="settings-help"><?php esc_html_e( 'No other users are available.', 'traveler' ); ?></p>
                                <?php else : ?>
                                    <?php foreach ( $trip_editor_candidates as $editor_candidate ) : ?>
                                        <label class="setting-option">
                                            <input type="checkbox" name="trip_editor_ids[]" value="<?php echo esc_attr( (string) $editor_candidate->ID ); ?>" <?php checked( in_array( (int) $editor_candidate->ID, $trip_editor_ids, true ) ); ?>>
                                            <span>
                                                <strong<?php echo App::mask_attr( 'person', (string) $editor_candidate->ID ); ?>><?php echo esc_html( $editor_candidate->display_name ); ?></strong>
                                                <span<?php echo App::mask_attr( 'email', (string) $editor_candidate->ID ); ?>><?php echo esc_html( $editor_candidate->user_email ); ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="settings-form-actions">
                                    <button type="submit"><?php esc_html_e( 'Save Editors', 'traveler' ); ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="travel-journaling-zone" aria-labelledby="travel-journaling-heading">
                    <details>
                        <summary><h2 id="travel-journaling-heading"><?php esc_html_e( 'Travel Journaling', 'traveler' ); ?></h2></summary>
                        <p class="settings-help">
                            <?php esc_html_e( 'You can create journal entries per day. Those entries start off completely private. When you want to publish one, use the Prepare for Publishing button. This will create a draft post that you can then publish.', 'traveler' ); ?>
                        </p>
                        <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="traveler_update_trip">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                            <input type="hidden" name="trip_journal_enabled_present" value="1">
                            <?php wp_nonce_field( 'traveler_update_trip_' . $trip_data['id'] ); ?>
                            <label class="setting-option">
                                <input type="checkbox" name="trip_journal_enabled" value="1" <?php checked( $journal_enabled ); ?>>
                                <span>
                                    <strong><?php esc_html_e( 'Enable Travel Journaling', 'traveler' ); ?></strong>
                                </span>
                            </label>
                            <?php if ( $journal_enabled ) : ?>
                                <input type="hidden" name="trip_journal_publishing_defaults_present" value="1">
                                <label for="trip_journal_category_id">
                                    <?php esc_html_e( 'Journal post category', 'traveler' ); ?>
                                    <select id="trip_journal_category_id" name="trip_journal_category_id">
                                        <option value="0"><?php esc_html_e( 'No default category', 'traveler' ); ?></option>
                                        <?php foreach ( $journal_categories as $journal_category ) : ?>
                                            <option value="<?php echo esc_attr( (string) $journal_category->term_id ); ?>" <?php selected( $journal_category_id, (int) $journal_category->term_id ); ?>>
                                                <?php echo esc_html( $journal_category->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="trip_journal_tags">
                                    <?php esc_html_e( 'Journal post tags', 'traveler' ); ?>
                                    <input type="text" id="trip_journal_tags" name="trip_journal_tags" value="<?php echo esc_attr( $journal_tags ); ?>" placeholder="<?php esc_attr_e( 'travel, trip-name', 'traveler' ); ?>">
                                </label>
                            <?php endif; ?>
                            <div class="settings-form-actions">
                                <button type="submit"><?php esc_html_e( 'Save Travel Journaling', 'traveler' ); ?></button>
                            </div>
                        </form>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline && current_user_can( 'delete_traveler_trip', $trip_id ) ) : ?>
                <section class="danger-zone" aria-labelledby="delete-heading">
                    <details>
                        <summary><h2 id="delete-heading"><?php esc_html_e( 'Delete Travel Plan', 'traveler' ); ?></h2></summary>
                        <p><?php esc_html_e( 'This deletes the travel plan and moves its itinerary items to the trash.', 'traveler' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync onsubmit="return confirm('<?php echo esc_js( __( 'Delete this travel plan?', 'traveler' ) ); ?>');">
                            <input type="hidden" name="action" value="traveler_delete">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <?php wp_nonce_field( 'traveler_delete_' . $trip_data['id'] ); ?>
                            <button class="delete-button" type="submit"><?php esc_html_e( 'Delete Travel Plan', 'traveler' ); ?></button>
                        </form>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <details class="offline-panel" data-offline-panel>
                    <summary><h2 id="offline-heading"><?php esc_html_e( 'Offline', 'traveler' ); ?></h2></summary>
                    <dl class="offline-grid">
                        <div>
                            <dt><?php esc_html_e( 'Connection', 'traveler' ); ?></dt>
                            <dd data-offline-connection><?php esc_html_e( 'Checking', 'traveler' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Service worker', 'traveler' ); ?></dt>
                            <dd data-offline-worker><?php esc_html_e( 'Checking', 'traveler' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Current page', 'traveler' ); ?></dt>
                            <dd data-offline-cache><?php esc_html_e( 'Checking', 'traveler' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Cached files', 'traveler' ); ?></dt>
                            <dd data-offline-files><?php esc_html_e( 'Checking', 'traveler' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Queued changes', 'traveler' ); ?></dt>
                            <dd data-offline-queue><?php esc_html_e( 'Checking', 'traveler' ); ?></dd>
                        </div>
                    </dl>
                </details>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <div class="bottom-nav">
                    <a href="<?php echo esc_url( home_url( '/traveler/' ) ); ?>"><?php esc_html_e( 'Back to Traveler', 'traveler' ); ?></a>
                </div>
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
                            window.alert('<?php echo esc_js( __( 'Select one continuous lodging date range.', 'traveler' ) ); ?>');
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
                var status = control.querySelector('[data-share-status]');
                var copyResetTimers = {};

                copyButtons.forEach(function(button) {
                    button.setAttribute('data-share-default-text', button.textContent);
                });

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

                function getShareButtonKey(button) {
                    return (button.getAttribute('data-share-mode') || 'fellow') + ':' + (button.getAttribute('data-share-kind') || 'timeline');
                }

                function setShareUrls(mode, timelineUrl, calendarUrl) {
                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.setAttribute('data-share-url', (button.getAttribute('data-share-kind') || 'timeline') === 'calendar' ? (calendarUrl || '') : (timelineUrl || ''));
                        }
                    });

                    removeButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.hidden = !timelineUrl;
                        }
                    });

                    resetCopyButton(mode);
                }

                function resetCopyButton(mode) {
                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.textContent = button.getAttribute('data-share-default-text') || button.textContent;
                            button.classList.remove('copied');
                        }
                    });
                }

                function confirmCopied(button) {
                    var mode = button.getAttribute('data-share-mode') || 'fellow';
                    var timerKey = getShareButtonKey(button);

                    copyButtons.forEach(function(button) {
                        if ((button.getAttribute('data-share-mode') || 'fellow') === mode) {
                            button.textContent = button.getAttribute('data-share-default-text') || button.textContent;
                            button.classList.remove('copied');
                        }
                    });
                    button.textContent = '<?php echo esc_js( __( 'Copied!', 'traveler' ) ); ?>';
                    button.classList.add('copied');
                    setStatus((button.getAttribute('data-share-kind') || 'timeline') === 'calendar' ? '<?php echo esc_js( __( 'Calendar subscription link copied.', 'traveler' ) ); ?>' : '<?php echo esc_js( __( 'Share link copied.', 'traveler' ) ); ?>');

                    if (copyResetTimers[timerKey]) {
                        window.clearTimeout(copyResetTimers[timerKey]);
                    }

                    copyResetTimers[timerKey] = window.setTimeout(function() {
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
                                throw new Error(data && data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'The sharing change could not be saved.', 'traveler' ) ); ?>');
                            }

                            return data.data || {};
                        });
                    }).then(function(data) {
                        if (data.mode) {
                            setShareUrls(data.mode, data.url || '', data.calendar_url || '');
                        }
                        setStatus(data.message || '');
                        return data;
                    }).catch(function(error) {
                        setStatus(error.message || '<?php echo esc_js( __( 'The sharing change could not be saved.', 'traveler' ) ); ?>');
                        throw error;
                    }).finally(function() {
                        setBusy(false);
                    });
                }

                removeButtons.forEach(function(removeButton) {
                    removeButton.addEventListener('click', function() {
                        requestShareAction('traveler_remove_share_link', removeButton.getAttribute('data-share-mode') || 'fellow');
                    });
                });

                function copyShareUrl(url, button) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        return navigator.clipboard.writeText(url).then(function() {
                            confirmCopied(button);
                        }).catch(function() {
                            window.prompt('<?php echo esc_js( __( 'Copy this link:', 'traveler' ) ); ?>', url);
                            confirmCopied(button);
                        });
                    }

                    window.prompt('<?php echo esc_js( __( 'Copy this link:', 'traveler' ) ); ?>', url);
                    confirmCopied(button);
                    return Promise.resolve();
                }

                copyButtons.forEach(function(copyButton) {
                    copyButton.addEventListener('click', function() {
                        var mode = copyButton.getAttribute('data-share-mode') || 'fellow';
                        var kind = copyButton.getAttribute('data-share-kind') || 'timeline';
                        var url = copyButton.getAttribute('data-share-url') || '';

                        if (url) {
                            copyShareUrl(url, copyButton);
                            return;
                        }

                        copyButton.textContent = '<?php echo esc_js( __( 'Generating...', 'traveler' ) ); ?>';
                        requestShareAction('traveler_generate_share_link', mode).then(function(data) {
                            var generatedUrl = data ? (kind === 'calendar' ? data.calendar_url : data.url) : '';
                            if (generatedUrl) {
                                copyShareUrl(generatedUrl, copyButton);
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
        <?php $static_timeline_script = $traveler->get_static_timeline_script(); ?>
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
