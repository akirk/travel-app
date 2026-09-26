<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
use TravelApp\App;
use TravelApp\LodgingCoverage;
use TravelApp\Parser\AiParser;
use TravelApp\Trip;

$travel_app = App::get_instance();
$travel_app_template_context = isset( $travel_app_template_context ) && is_array( $travel_app_template_context ) ? $travel_app_template_context : [];
$demo_mode_enabled = $travel_app->is_demo_mode_enabled();
$trip_id    = isset( $travel_app_template_context['trip_id'] ) ? absint( $travel_app_template_context['trip_id'] ) : absint( $travel_app->get_route_param( 'id' ) );
$share_token = isset( $travel_app_template_context['share_token'] ) ? sanitize_text_field( (string) $travel_app_template_context['share_token'] ) : $travel_app->get_route_param( 'token' );
$is_static_download = ! empty( $travel_app_template_context['is_static_download'] );
$is_shared_timeline = ! empty( $travel_app_template_context['is_shared_timeline'] ) || '' !== $share_token;
$is_readonly_timeline = $is_shared_timeline || $is_static_download;
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'travel-app' ),
        esc_html__( 'Travel plan not found', 'travel-app' ),
        [ 'response' => 404 ]
    );
}
$share_mode = $is_static_download ? ( isset( $travel_app_template_context['static_share_mode'] ) ? (string) $travel_app_template_context['static_share_mode'] : 'fellow' ) : ( $is_shared_timeline ? $travel_app->get_trip_share_mode_by_token( $trip_id, $share_token ) : '' );
$show_private_share_details = ( ! $is_shared_timeline && ! $is_static_download ) || 'fellow' === $share_mode;
$error      = $travel_app->get_query_arg_key( 'travel_app_error' );
$quick_plan_draft_key = $travel_app->get_query_arg_key( 'quick_plan_draft' );
$quick_plan_draft = '' !== $quick_plan_draft_key ? $travel_app->get_quick_plan_draft( $quick_plan_draft_key ) : [];
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
$traveller_label = $travel_app->get_trip_traveller_label( $trip_data );
$editable_trip_data = [];
if ( ! $is_readonly_timeline ) {
    $editable_trip_data = $trip_data;
    $editable_trip_data['segments'] = array_map( static function( array $editable_segment ) use ( $trip_data ): array {
        $editable_index = (int) ( $editable_segment['id'] ?? 0 );
        $editable_segment['edit_nonce'] = wp_create_nonce( 'travel_app_update_segment_' . (int) $trip_data['id'] . '_' . $editable_index );
        $editable_segment['delete_nonce'] = wp_create_nonce( 'travel_app_delete_segment_' . (int) $trip_data['id'] . '_' . $editable_index );

        return $editable_segment;
    }, $segments );
}
$is_trip_active = $travel_app->is_trip_active( $trip_data );
$show_now_next_section = '0' !== (string) get_term_meta( $trip_id, '_travel_app_show_now_next', true );
$journal_enabled = '1' === (string) get_term_meta( $trip_id, '_travel_app_journal_enabled', true );
$journal_entries_by_day = ( ! $is_readonly_timeline && $journal_enabled ) ? $travel_app->get_journal_entries_for_trip( $trip_id ) : [];
$journal_category_id = absint( get_term_meta( $trip_id, '_travel_app_journal_category_id', true ) );
$journal_tags = (string) get_term_meta( $trip_id, '_travel_app_journal_tags', true );
$can_manage_trip_editors = ! $is_readonly_timeline && $travel_app->current_user_can_manage_trip_editors( $trip_id );
$trip_editor_ids = $can_manage_trip_editors ? $travel_app->get_trip_editor_ids( $trip_id ) : [];
$trip_editor_candidates = $can_manage_trip_editors ? $travel_app->get_trip_editor_candidates( $trip_id ) : [];
$journal_categories = ! $is_readonly_timeline ? get_categories( [
    'hide_empty' => false,
] ) : [];
$fellow_share_url = ! $is_shared_timeline ? $travel_app->get_trip_share_url( (int) $trip_data['id'], 'fellow' ) : '';
$public_share_url = ! $is_shared_timeline ? $travel_app->get_trip_share_url( (int) $trip_data['id'], 'public' ) : '';
$fellow_calendar_url = ! $is_shared_timeline ? $travel_app->get_trip_calendar_url( (int) $trip_data['id'], 'fellow' ) : '';
$public_calendar_url = ! $is_shared_timeline ? $travel_app->get_trip_calendar_url( (int) $trip_data['id'], 'public' ) : '';
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

// The map page is only reachable with an account, so a shared or downloaded
// timeline does not offer it.
$trip_direct_map_url = '';
if ( count( $route_locations ) >= 2 && ! $is_readonly_timeline ) {
    $trip_direct_map_url = home_url( '/travel-app/trip/' . (int) $trip_data['id'] . '/map/' );
}

if ( ! $is_static_download ) {
    $travel_app->enqueue_template_assets(
        'trip',
        ! $is_readonly_timeline,
        ! $is_readonly_timeline ? 'travelAppTripData' : '',
        [
            'continuousLodgingRange' => __( 'Select one continuous lodging date range.', 'travel-app' ),
            'copied'                 => __( 'Copied!', 'travel-app' ),
            'calendarCopied'         => __( 'Calendar subscription link copied.', 'travel-app' ),
            'shareCopied'            => __( 'Share link copied.', 'travel-app' ),
            'shareFailed'            => __( 'The sharing change could not be saved.', 'travel-app' ),
            'copyPrompt'             => __( 'Copy this link:', 'travel-app' ),
            'generating'             => __( 'Generating...', 'travel-app' ),
        ]
    );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( $trip_data ? $trip_data['title'] : __( 'Travel Plan', 'travel-app' ) ); ?></title>
    <?php if ( ! $is_static_download ) : ?>
        <link rel="manifest" href="<?php echo esc_url( $travel_app->get_manifest_url( (int) $trip_data['id'], $share_token ) ); ?>">
        <meta name="theme-color" content="<?php echo esc_attr( $travel_app->get_theme_color() ); ?>">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $trip_data['title'] ?: __( 'Timeline', 'travel-app' ) ); ?>">
    <?php else : ?>
        <?php $travel_app->print_static_trip_styles(); ?>
    <?php endif; ?>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_head(); ?>
    <?php endif; ?>
</head>
<body>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_body_open(); ?>
    <?php endif; ?>

    <main>
        <?php if ( ! $is_readonly_timeline && $error ) : ?>
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
                    <h1><span<?php echo esc_attr( App::mask_attr( 'title', (string) $trip_data['id'] ) ); ?>><?php echo esc_html( $trip_data['title'] ); ?></span></h1>
                    <?php if ( ! $is_readonly_timeline ) : ?>
                        <button class="trip-title-edit-button" type="button" data-trip-title-edit aria-controls="trip-title-form" aria-expanded="false" title="<?php esc_attr_e( 'Edit travel plan title', 'travel-app' ); ?>">
                            <span aria-hidden="true">✎</span>
                            <span class="screen-reader-text"><?php esc_html_e( 'Edit travel plan title', 'travel-app' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ( ! $is_readonly_timeline ) : ?>
                    <form class="trip-title-form" id="trip-title-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync hidden>
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
                    <?php if ( '' !== $traveller_label ) : ?>
                        <span<?php echo esc_attr( App::mask_attr( 'person', (string) ( $trip_data['owner_id'] ?? '' ) ) ); ?>><?php echo esc_html( $traveller_label ); ?></span>
                    <?php endif; ?>
                    <?php foreach ( $travel_app->get_trip_summary_parts( $trip_data, null, ! $is_static_download ) as $summary_part ) : ?>
                        <span><?php echo esc_html( $summary_part ); ?></span>
                    <?php endforeach; ?>
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of itinerary items. */
                                _n( '%d item', '%d items', count( $segments ), 'travel-app' ),
                                count( $segments )
                            )
                        );
                        ?>
                    </span>
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

            <?php if ( ! $is_static_download ) : ?>
                <div class="offline-status" data-offline-status role="status" aria-live="polite" hidden></div>
            <?php endif; ?>

            <?php
            $demo_control_id = 'trip-' . (string) $trip_data['id'];
            $demo_control_value = $demo_start_time;
            ?>
            <?php if ( $show_now_next_section && $is_trip_active && ! empty( $timeline_segments ) ) : ?>
                <section class="panel now-next-panel" aria-label="<?php esc_attr_e( 'Now and Next', 'travel-app' ); ?>">
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
                                ? $travel_app->format_time_range_label( (string) ( $step['time'] ?? '' ), $step_end_time )
                                : (string) ( $step['time'] ?? '' );
                            $step_start_label = trim( $travel_app->format_date_label( $step_date ) . ' ' . (string) ( $step['time'] ?? '' ) );
                            $step_end_label = '' !== $step_effective_end_date && $step_effective_end_date !== $step_date
                                ? trim( $travel_app->format_date_label( $step_effective_end_date ) . ' ' . $step_end_time )
                                : '';
                            $step_show_location = 'checkout' !== $step_timeline_kind && ( $show_private_share_details || $is_transport_segment( $step ) );
                            $step_location = $step_show_location ? (string) ( $step['location'] ?? '' ) : '';
                            $step_end_location = $step_show_location ? (string) ( $step['end_location'] ?? '' ) : '';
                            $step_title = (string) ( $step['title'] ?? '' );
                            if ( 'checkout' === $step_timeline_kind ) {
                                $step_title = '' !== $step_title
                                    /* translators: %s: name of the lodging being checked out of. */
                                    ? sprintf( __( 'Check out: %s', 'travel-app' ), $step_title )
                                    : __( 'Check out', 'travel-app' );
                            } elseif ( 'return' === $step_timeline_kind ) {
                                $step_title = '' !== $step_title
                                    /* translators: %s: name of the rental car being returned. */
                                    ? sprintf( __( 'Return car: %s', 'travel-app' ), $step_title )
                                    : __( 'Return car', 'travel-app' );
                            }
                            ?>
                            <span hidden data-preview-item data-url="<?php echo esc_url( '#' . $step_anchor ); ?>" data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-timeline-kind="<?php echo esc_attr( $step_timeline_kind ); ?>" data-type="<?php echo esc_attr( (string) ( $step['type'] ?? '' ) ); ?>" data-date="<?php echo esc_attr( $step_date ); ?>" data-time-label="<?php echo esc_attr( $step_time_label ); ?>" data-date-time-label="<?php echo esc_attr( $step_start_label ); ?>" data-end-date="<?php echo esc_attr( $step_effective_end_date ); ?>" data-end-time="<?php echo esc_attr( $step_end_time ); ?>" data-end-label="<?php echo esc_attr( $step_end_label ); ?>" data-location="<?php echo esc_attr( $step_location ); ?>" data-end-location="<?php echo esc_attr( $step_end_location ); ?>" data-title="<?php echo esc_attr( $step_title ); ?>"></span>
                        <?php endforeach; ?>
                        <?php foreach ( [ 'current' => __( 'Now', 'travel-app' ), 'next' => __( 'Next', 'travel-app' ) ] as $key => $label ) : ?>
                            <a class="mini-step <?php echo esc_attr( $key ); ?>" href="#" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-slot-label="<?php echo esc_attr( $label ); ?>" data-ended-label="<?php esc_attr_e( 'Last', 'travel-app' ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'travel-app' ); ?>">
                                <div class="mini-label" data-preview-label><?php echo esc_html( $label ); ?></div>
                                <div class="mini-title" data-preview-title<?php echo esc_attr( App::mask_attr( 'title' ) ); ?>><?php esc_html_e( 'No item', 'travel-app' ); ?></div>
                                <div class="mini-countdown" data-preview-countdown></div>
                                <div class="mini-location" data-preview-meta<?php echo esc_attr( App::mask_attr( 'text' ) ); ?>></div>
                                <div class="mini-location" data-preview-location<?php echo esc_attr( App::mask_attr( 'place' ) ); ?>></div>
                                <div class="mini-location" data-preview-end<?php echo esc_attr( App::mask_attr( 'text' ) ); ?>></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel timeline-panel" aria-labelledby="timeline-heading" data-ai-assistant-important>
                <div class="timeline-header">
                    <h2 id="timeline-heading"><?php esc_html_e( 'Timeline', 'travel-app' ); ?></h2>
                    <div class="timeline-header-actions">
                        <?php if ( '' !== $trip_direct_map_url ) : ?>
                            <a class="timeline-map-link" href="<?php echo esc_url( $trip_direct_map_url ); ?>" title="<?php esc_attr_e( 'Route map on OpenStreetMap', 'travel-app' ); ?>">
                                <span aria-hidden="true">&#x1F5FA;</span>
                                <?php esc_html_e( 'Map', 'travel-app' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $is_trip_active ) : ?>
                            <button class="ghost-button timeline-now-button" type="button" data-timeline-now aria-controls="timeline" aria-label="<?php esc_attr_e( 'Jump to current time', 'travel-app' ); ?>" title="<?php esc_attr_e( 'Jump to current time', 'travel-app' ); ?>" disabled>
                                <?php esc_html_e( 'Now', 'travel-app' ); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ( ! $is_readonly_timeline ) : ?>
                            <button class="add-item-button" type="button" data-add-item-toggle aria-controls="add-item-form" aria-expanded="<?php echo ! empty( $quick_plan_segment ) ? 'true' : 'false'; ?>">
                                <?php esc_html_e( '+ Add Item', 'travel-app' ); ?>
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
                                    <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $covered_lodging_night['item_id'] ?? 0 ) . '-item' ) ); ?>><?php echo esc_html( $covered_item_label ); ?></span>
                                    <?php if ( isset( $segment_type_labels[ $covered_item_type ] ) ) : ?>
                                        · <?php echo esc_html( $segment_type_labels[ $covered_item_type ] ); ?>
                                    <?php endif; ?>
                                </span>
                                <span class="lodging-checker-brief"<?php echo esc_attr( App::mask_attr( 'place', (string) ( $covered_lodging_night['item_id'] ?? 0 ) . '-location' ) ); ?>><?php echo esc_html( (string) ( $covered_lodging_night['location'] ?? '' ) ); ?></span>
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
                            <summary><?php esc_html_e( 'Import or Add from Text', 'travel-app' ); ?></summary>
                            <form class="trip-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="travel_app_import">
                                <input type="hidden" name="import_trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <?php wp_nonce_field( 'travel_app_import' ); ?>
                                <label for="trip_import_text">
                                    <?php
                                    printf(
                                        /* translators: %s: trip title. */
                                        esc_html__( 'Paste confirmation, file text, or a typed entry for %s', 'travel-app' ),
                                        esc_html( $trip_data['title'] )
                                    );
                                    ?>
                                </label>
                                <textarea id="trip_import_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Example: Dinner in Hamburg on August 2 at 7pm...', 'travel-app' ); ?>"></textarea>
                                <p class="hint"><?php echo esc_html( $has_ai ? __( 'AI extraction can turn plain text into an entry for review; confirmations still work too.', 'travel-app' ) : __( 'Uses quick parsing or a basic parser.', 'travel-app' ) ); ?></p>
                                <div class="form-actions">
                                    <button type="submit"><?php esc_html_e( 'Review Import', 'travel-app' ); ?></button>
                                </div>
                            </form>
                        </details>

                        <form
                            class="edit-form add-item-form"
                            method="post"
                            action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                            <?php if ( empty( $quick_plan_segment ) ) : ?>
                                data-offline-sync
                            <?php endif; ?>
                            <?php if ( empty( $quick_plan_segment ) && current_user_can( 'edit_travel_app_trip', $trip_id ) ) : ?>
                                toolname="prepareTravelItem"
                                tooldescription="<?php esc_attr_e( 'Fill in a new itinerary item for this travel plan. The user reviews the form and selects Add Item to save it.', 'travel-app' ); ?>"
                            <?php endif; ?>
                        >
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
                                        esc_html__( 'Prefilled from text. Review the fields before adding this entry. Parsed with: %s.', 'travel-app' ),
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
                                <input name="segment_title" toolparamdescription="<?php esc_attr_e( 'Short title of the itinerary item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['title'] ?? '' ) ); ?>">
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'Type', 'travel-app' ); ?>
                                <select name="segment_type" toolparamdescription="<?php esc_attr_e( 'Kind of travel itinerary item.', 'travel-app' ); ?>">
                                    <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $quick_plan_segment['type'] ?? 'activity', $type ); ?>><?php echo esc_html( $segment_type_labels[ $type ] ?? ucfirst( $type ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field-wide">
                                <?php esc_html_e( 'URL', 'travel-app' ); ?>
                                <input type="url" name="segment_url" toolparamdescription="<?php esc_attr_e( 'Optional booking or reference URL for this item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['url'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'Location', 'travel-app' ); ?>
                                <input name="segment_location" toolparamdescription="<?php esc_attr_e( 'Starting place or venue for this item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['location'] ?? '' ) ); ?>">
                            </label>
                            <label>
                                <?php esc_html_e( 'End Location', 'travel-app' ); ?>
                                <input name="segment_end_location" toolparamdescription="<?php esc_attr_e( 'Destination or ending place, when different from the start.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_location'] ?? '' ) ); ?>">
                            </label>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'Start Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_date" toolparamdescription="<?php esc_attr_e( 'Start date of the item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_time" toolparamdescription="<?php esc_attr_e( 'Local start time of the item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <div class="date-time-group">
                                <label>
                                    <?php esc_html_e( 'End Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_end_date" toolparamdescription="<?php esc_attr_e( 'End date, if the item spans multiple days.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_end_time" toolparamdescription="<?php esc_attr_e( 'Local end time of the item.', 'travel-app' ); ?>" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_time'] ?? '' ) ); ?>">
                                </label>
                            </div>
                            <label class="field-wide">
                                <?php esc_html_e( 'Details', 'travel-app' ); ?>
                                <textarea name="segment_details" toolparamdescription="<?php esc_attr_e( 'Optional notes, booking details, or instructions.', 'travel-app' ); ?>"><?php echo esc_textarea( (string) ( $quick_plan_segment['details'] ?? '' ) ); ?></textarea>
                            </label>
                            <div class="form-actions">
                                <button type="submit"><?php echo esc_html( ! empty( $quick_plan_segment ) ? __( 'Add to This Trip', 'travel-app' ) : __( 'Add Item', 'travel-app' ) ); ?></button>
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
                    <template id="travel-app-trip-data"><?php echo esc_html( wp_json_encode( $editable_trip_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?></template>
                    <template id="segment-edit-template">
                        <?php
                        $segment = $segment_form_template_segment;
                        $index = $segment_form_template_index;
                        require __DIR__ . '/partials/segment-form.php';
                        ?>
                        <p class="attachment-note"><?php esc_html_e( 'Attachments can be opened from the timeline. Uploading or deleting attachments requires an online connection.', 'travel-app' ); ?></p>
                    </template>
                <?php endif; ?>

                <?php if ( empty( $segments_by_day ) ) : ?>
                    <p class="empty"><?php esc_html_e( 'No timeline items were found.', 'travel-app' ); ?></p>
                <?php else : ?>
                    <div class="timeline" id="timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>"<?php echo $is_readonly_timeline ? ' data-readonly-timeline="1"' : ''; ?><?php echo $is_trip_active ? ' data-current-time="1" data-current-time-value="' . esc_attr( $timeline_current_time_value ) . '" data-current-time-captured="' . esc_attr( $timeline_current_time_captured ) . '"' : ''; ?>>
                        <?php if ( $show_timeline_time_marker ) : ?>
                            <div class="time-marker"><span class="time-marker-label"></span></div>
                        <?php endif; ?>
                        <?php foreach ( $segments_by_day as $day => $day_segments ) : ?>
                            <?php
                            $journal_entry = $journal_entries_by_day[ $day ] ?? [];
                            $journal_exists = ! empty( $journal_entry );
                            ?>
                            <section class="timeline-day<?php echo empty( $day_segments ) ? ' empty' : ''; ?>" data-date="<?php echo esc_attr( $day ); ?>">
                                <div class="day-heading-row">
                                    <h3 class="day-heading"><?php echo esc_html( $travel_app->format_date_label( $day ) ); ?></h3>
                                    <?php if ( ! $is_readonly_timeline && $journal_enabled ) : ?>
                                        <div class="day-journal-actions">
                                            <form class="day-journal-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                <input type="hidden" name="action" value="travel_app_open_journal_entry">
                                                <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                                <input type="hidden" name="journal_date" value="<?php echo esc_attr( $day ); ?>">
                                                <?php wp_nonce_field( 'travel_app_open_journal_entry_' . $trip_data['id'] ); ?>
                                                <button class="day-journal-button" type="submit">
                                                    <?php echo esc_html( $journal_exists ? __( 'Edit Journal', 'travel-app' ) : __( 'Start Journal', 'travel-app' ) ); ?>
                                                </button>
                                            </form>
                                            <?php if ( $journal_exists ) : ?>
                                                <form class="day-journal-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                    <input type="hidden" name="action" value="travel_app_prepare_journal_post">
                                                    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                                    <input type="hidden" name="journal_id" value="<?php echo esc_attr( (string) ( $journal_entry['id'] ?? 0 ) ); ?>">
                                                    <?php wp_nonce_field( 'travel_app_prepare_journal_post_' . $trip_data['id'] . '_' . (int) ( $journal_entry['id'] ?? 0 ) ); ?>
                                                    <button class="day-journal-button" type="submit">
                                                        <?php echo esc_html( ! empty( $journal_entry['post_id'] ) ? __( 'Update Linked Post', 'travel-app' ) : __( 'Prepare for Publishing', 'travel-app' ) ); ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
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
                                        <div class="timeline-item" data-inline-edit-view data-date="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>" data-time="<?php echo esc_attr( (string) ( $segment['time'] ?? '' ) ); ?>" data-end-date="<?php echo esc_attr( (string) ( $segment['end_date'] ?? '' ) ); ?>" data-end-time="<?php echo esc_attr( (string) ( $segment['end_time'] ?? '' ) ); ?>" data-datetime="<?php echo esc_attr( $segment_datetime ); ?>">
                                            <div class="timeline-meta">
                                                <div class="time"><?php echo esc_html( $segment['time'] ?: ' ' ); ?></div>
                                                <div class="type"><?php echo esc_html( $type_label ); ?></div>
                                            </div>
                                            <div>
                                                <div class="timeline-title-row title">
                                                    <?php if ( $is_readonly_timeline ) : ?>
                                                        <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                                    <?php elseif ( ! $is_end_timeline_entry ) : ?>
                                                        <button class="timeline-title-button" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                            <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                                        </button>
                                                    <?php else : ?>
                                                        <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
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
                                                            <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ) ); ?>><?php echo esc_html( $location ); ?></span>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                    <?php $end_location = (string) $segment['end_location']; ?>
                                                    <div class="detail">
                                                        <?php esc_html_e( 'To:', 'travel-app' ); ?>
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span aria-hidden="true">&#x1F4CD;</span>
                                                            <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ) ); ?>><?php echo esc_html( $end_location ); ?></span>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $show_private_share_details && ! empty( $segment['details'] ) ) : ?>
                                                    <div class="detail timeline-note"<?php echo esc_attr( App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-details' ) ); ?>><?php echo esc_html( $segment['details'] ); ?></div>
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
                                                            <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php
                                                            echo esc_attr(
                                                                sprintf(
                                                                    /* translators: %s: attachment file name. */
                                                                    __( 'Download %s', 'travel-app' ),
                                                                    $attachment_label
                                                                )
                                                            );
                                                            ?>" data-offline-cache-url>
                                                                <span aria-hidden="true">↓</span>
                                                                <span<?php echo esc_attr( App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-attachment-' . (string) ( $attachment['id'] ?? md5( $attachment_label ) ) ) ); ?>><?php echo esc_html( $attachment_label ); ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ( $has_url_preview ) : ?>
                                                    <?php $has_url_preview_image = ! empty( $url_preview['image'] ); ?>
                                                    <a class="url-preview<?php echo $has_url_preview_image ? '' : ' no-image'; ?>" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php if ( $has_url_preview_image ) : ?>
                                                            <img class="url-preview-image"<?php echo esc_attr( App::mask_attr( 'image', (string) ( $segment['id'] ?? $index ) . '-preview' ) ); ?> src="<?php echo esc_url( (string) $url_preview['image'] ); ?>" alt="" loading="lazy">
                                                        <?php endif; ?>
                                                        <div class="url-preview-text">
                                                            <?php if ( ! empty( $url_preview['site_name'] ) ) : ?>
                                                                <div class="url-preview-meta"><?php echo esc_html( (string) $url_preview['site_name'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['title'] ) ) : ?>
                                                                <div class="url-preview-title"<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-preview' ) ); ?>><?php echo esc_html( (string) $url_preview['title'] ); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ( ! empty( $url_preview['description'] ) ) : ?>
                                                                <div class="url-preview-description"<?php echo esc_attr( App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-preview-description' ) ); ?>><?php echo esc_html( (string) $url_preview['description'] ); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ( ! $is_readonly_timeline && ! $is_end_timeline_entry ) : ?>
                                            <div class="timeline-edit-panel" id="<?php echo esc_attr( 'edit-segment-' . $index ); ?>" data-inline-edit-panel hidden>
                                            </div>
                                        <?php endif; ?>
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
                            <div class="item unscheduled-link" id="segment-<?php echo esc_attr( (string) $index ); ?>" data-inline-edit-view>
                                    <div class="summary-grid">
                                        <span class="time"><?php echo esc_html( trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) ) ); ?></span>
                                        <span>
                                            <span class="type"><?php echo esc_html( $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) ) ); ?></span><br>
                                            <?php if ( $is_readonly_timeline ) : ?>
                                                <span class="title"<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                            <?php else : ?>
                                                <button class="timeline-title-button title" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                    <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $segment['end_date'] ) ) : ?>
                                                <br><span class="detail"><?php echo esc_html( $travel_app->get_segment_date_range_label( $segment ) ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( $show_location && ! empty( $segment['location'] ) ) : ?>
                                                <?php $location = (string) $segment['location']; ?>
                                                <br><span class="detail">
                                                    <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <span aria-hidden="true">&#x1F4CD;</span>
                                                        <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ) ); ?>><?php echo esc_html( $location ); ?></span>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                <?php $end_location = (string) $segment['end_location']; ?>
                                                <br><span class="detail">
                                                    <?php esc_html_e( 'To:', 'travel-app' ); ?>
                                                    <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <span aria-hidden="true">&#x1F4CD;</span>
                                                        <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ) ); ?>><?php echo esc_html( $end_location ); ?></span>
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
                                                        <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php
                                                            echo esc_attr(
                                                                sprintf(
                                                                    /* translators: %s: attachment file name. */
                                                                    __( 'Download %s', 'travel-app' ),
                                                                    $attachment_label
                                                                )
                                                            );
                                                            ?>" data-offline-cache-url>
                                                            <span aria-hidden="true">↓</span>
                                                            <span<?php echo esc_attr( App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-attachment-' . (string) ( $attachment['id'] ?? md5( $attachment_label ) ) ) ); ?>><?php echo esc_html( $attachment_label ); ?></span>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ( ! $is_readonly_timeline ) : ?>
                                            <button class="ghost-button" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                <?php esc_html_e( 'Edit', 'travel-app' ); ?>
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
                                        <?php esc_html_e( 'HTML', 'travel-app' ); ?>
                                    </a>
                                    <?php if ( ! $travel_app->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="timeline" data-share-mode="fellow" data-share-url="<?php echo esc_attr( $fellow_share_url ); ?>"><?php esc_html_e( 'URL', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="calendar" data-share-mode="fellow" data-share-url="<?php echo esc_attr( $fellow_calendar_url ); ?>"><?php esc_html_e( 'ICS', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="fellow" <?php echo '' === $fellow_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Stop sharing', 'travel-app' ); ?></button>
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
                                        <?php esc_html_e( 'HTML', 'travel-app' ); ?>
                                    </a>
                                    <?php if ( ! $travel_app->is_playground() ) : ?>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="timeline" data-share-mode="public" data-share-url="<?php echo esc_attr( $public_share_url ); ?>"><?php esc_html_e( 'URL', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-copy data-share-kind="calendar" data-share-mode="public" data-share-url="<?php echo esc_attr( $public_calendar_url ); ?>"><?php esc_html_e( 'ICS', 'travel-app' ); ?></button>
                                        <button class="ghost-button" type="button" data-share-remove data-share-mode="public" <?php echo '' === $public_share_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Stop sharing', 'travel-app' ); ?></button>
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
                <section class="settings-zone" aria-labelledby="settings-heading">
                    <details>
                        <summary><h2 id="settings-heading"><?php esc_html_e( 'Settings', 'travel-app' ); ?></h2></summary>
                        <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync>
                            <input type="hidden" name="action" value="travel_app_update_trip">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                            <input type="hidden" name="trip_show_now_next_present" value="1">
                            <?php wp_nonce_field( 'travel_app_update_trip_' . $trip_data['id'] ); ?>
                            <label class="setting-option">
                                <input type="checkbox" name="trip_show_now_next" value="1" <?php checked( $show_now_next_section ); ?>>
                                <span>
                                    <strong><?php esc_html_e( 'Show Now and Next', 'travel-app' ); ?></strong>
                                    <span><?php esc_html_e( 'Display the current and next itinerary items above the timeline while this trip is active.', 'travel-app' ); ?></span>
                                </span>
                            </label>
                            <div class="settings-form-actions">
                                <button type="submit"><?php esc_html_e( 'Save Settings', 'travel-app' ); ?></button>
                            </div>
                        </form>
                        <?php if ( $can_manage_trip_editors ) : ?>
                            <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <input type="hidden" name="action" value="travel_app_update_trip">
                                <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                                <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                                <input type="hidden" name="trip_editors_present" value="1">
                                <?php wp_nonce_field( 'travel_app_update_trip_' . $trip_data['id'] ); ?>
                                <p class="settings-help"><?php esc_html_e( 'Choose WordPress users who can modify this travel plan.', 'travel-app' ); ?></p>
                                <?php if ( empty( $trip_editor_candidates ) ) : ?>
                                    <p class="settings-help"><?php esc_html_e( 'No other users are available.', 'travel-app' ); ?></p>
                                <?php else : ?>
                                    <?php foreach ( $trip_editor_candidates as $editor_candidate ) : ?>
                                        <label class="setting-option">
                                            <input type="checkbox" name="trip_editor_ids[]" value="<?php echo esc_attr( (string) $editor_candidate->ID ); ?>" <?php checked( in_array( (int) $editor_candidate->ID, $trip_editor_ids, true ) ); ?>>
                                            <span>
                                                <strong<?php echo esc_attr( App::mask_attr( 'person', (string) $editor_candidate->ID ) ); ?>><?php echo esc_html( $editor_candidate->display_name ); ?></strong>
                                                <span<?php echo esc_attr( App::mask_attr( 'email', (string) $editor_candidate->ID ) ); ?>><?php echo esc_html( $editor_candidate->user_email ); ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="settings-form-actions">
                                    <button type="submit"><?php esc_html_e( 'Save Editors', 'travel-app' ); ?></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <section class="travel-journaling-zone" aria-labelledby="travel-journaling-heading">
                    <details>
                        <summary><h2 id="travel-journaling-heading"><?php esc_html_e( 'Travel Journaling', 'travel-app' ); ?></h2></summary>
                        <p class="settings-help">
                            <?php esc_html_e( 'You can create journal entries per day. Those entries start off completely private. When you want to publish one, use the Prepare for Publishing button. This will create a draft post that you can then publish.', 'travel-app' ); ?>
                        </p>
                        <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="travel_app_update_trip">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <input type="hidden" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>">
                            <input type="hidden" name="trip_journal_enabled_present" value="1">
                            <?php wp_nonce_field( 'travel_app_update_trip_' . $trip_data['id'] ); ?>
                            <label class="setting-option">
                                <input type="checkbox" name="trip_journal_enabled" value="1" <?php checked( $journal_enabled ); ?>>
                                <span>
                                    <strong><?php esc_html_e( 'Enable Travel Journaling', 'travel-app' ); ?></strong>
                                </span>
                            </label>
                            <?php if ( $journal_enabled ) : ?>
                                <input type="hidden" name="trip_journal_publishing_defaults_present" value="1">
                                <label for="trip_journal_category_id">
                                    <?php esc_html_e( 'Journal post category', 'travel-app' ); ?>
                                    <select id="trip_journal_category_id" name="trip_journal_category_id">
                                        <option value="0"><?php esc_html_e( 'No default category', 'travel-app' ); ?></option>
                                        <?php foreach ( $journal_categories as $journal_category ) : ?>
                                            <option value="<?php echo esc_attr( (string) $journal_category->term_id ); ?>" <?php selected( $journal_category_id, (int) $journal_category->term_id ); ?>>
                                                <?php echo esc_html( $journal_category->name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="trip_journal_tags">
                                    <?php esc_html_e( 'Journal post tags', 'travel-app' ); ?>
                                    <input type="text" id="trip_journal_tags" name="trip_journal_tags" value="<?php echo esc_attr( $journal_tags ); ?>" placeholder="<?php esc_attr_e( 'travel, trip-name', 'travel-app' ); ?>">
                                </label>
                            <?php endif; ?>
                            <div class="settings-form-actions">
                                <button type="submit"><?php esc_html_e( 'Save Travel Journaling', 'travel-app' ); ?></button>
                            </div>
                        </form>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline && current_user_can( 'delete_travel_app_trip', $trip_id ) ) : ?>
                <section class="danger-zone" aria-labelledby="delete-heading">
                    <details>
                        <summary><h2 id="delete-heading"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></h2></summary>
                        <p><?php esc_html_e( 'This deletes the travel plan and moves its itinerary items to the trash.', 'travel-app' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync data-confirm="<?php esc_attr_e( 'Delete this travel plan?', 'travel-app' ); ?>">
                            <input type="hidden" name="action" value="travel_app_delete">
                            <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                            <?php wp_nonce_field( 'travel_app_delete_' . $trip_data['id'] ); ?>
                            <button class="delete-button" type="submit"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></button>
                        </form>
                    </details>
                </section>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <details class="offline-panel" data-offline-panel>
                    <summary><h2 id="offline-heading"><?php esc_html_e( 'Offline', 'travel-app' ); ?></h2></summary>
                    <dl class="offline-grid">
                        <div>
                            <dt><?php esc_html_e( 'Connection', 'travel-app' ); ?></dt>
                            <dd data-offline-connection><?php esc_html_e( 'Checking', 'travel-app' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Service worker', 'travel-app' ); ?></dt>
                            <dd data-offline-worker><?php esc_html_e( 'Checking', 'travel-app' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Current page', 'travel-app' ); ?></dt>
                            <dd data-offline-cache><?php esc_html_e( 'Checking', 'travel-app' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Cached files', 'travel-app' ); ?></dt>
                            <dd data-offline-files><?php esc_html_e( 'Checking', 'travel-app' ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Queued changes', 'travel-app' ); ?></dt>
                            <dd data-offline-queue><?php esc_html_e( 'Checking', 'travel-app' ); ?></dd>
                        </div>
                    </dl>
                </details>
            <?php endif; ?>

            <?php if ( ! $is_readonly_timeline ) : ?>
                <div class="bottom-nav">
                    <a href="<?php echo esc_url( home_url( '/travel-app/' ) ); ?>"><?php esc_html_e( 'Back to Travel App', 'travel-app' ); ?></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_body_close(); ?>
    <?php endif; ?>
</body>
</html>
