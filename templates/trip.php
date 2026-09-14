<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
use Traveler\App;
use Traveler\LodgingCoverage;
use Traveler\Parser\AiParser;
use Traveler\Trip;

$traveler = App::get_instance();
$traveler_template_context = isset( $traveler_template_context ) && is_array( $traveler_template_context ) ? $traveler_template_context : [];
$demo_mode_enabled = $traveler->is_demo_mode_enabled();
$trip_id    = isset( $traveler_template_context['trip_id'] ) ? absint( $traveler_template_context['trip_id'] ) : absint( $traveler->get_route_param( 'id' ) );
$share_token = isset( $traveler_template_context['share_token'] ) ? sanitize_text_field( (string) $traveler_template_context['share_token'] ) : $traveler->get_route_param( 'token' );
$is_static_download = ! empty( $traveler_template_context['is_static_download'] );
$is_shared_timeline = ! empty( $traveler_template_context['is_shared_timeline'] ) || '' !== $share_token;
$is_readonly_timeline = $is_shared_timeline || $is_static_download;
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_traveler_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'traveler' ),
        esc_html__( 'Travel plan not found', 'traveler' ),
        [ 'response' => 404 ]
    );
}
$share_mode = $is_static_download ? ( isset( $traveler_template_context['static_share_mode'] ) ? (string) $traveler_template_context['static_share_mode'] : 'fellow' ) : ( $is_shared_timeline ? $traveler->get_trip_share_mode_by_token( $trip_id, $share_token ) : '' );
$show_private_share_details = ( ! $is_shared_timeline && ! $is_static_download ) || 'fellow' === $share_mode;
$error      = $traveler->get_query_arg_key( 'traveler_error' );
$quick_plan_draft_key = $traveler->get_query_arg_key( 'quick_plan_draft' );
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
// Decorative icons share one stroke system; the adjacent text supplies their meaning.
$render_timeline_icon = static function( string $name ): void {
    $paths = [
        'flight'   => 'M22 2 9 15M22 2l-8 20-5-7-7-5 20-8Z',
        'lodging'  => 'M3 18V7m18 11V7M3 14h18M5 14V9h14v5M7 9V6h10v3M3 18v3m18-3v3',
        'train'    => 'M7 3h10a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2ZM5 11h14M9 3v8m6-8v8M8 15h1m6 0h1M8 18l-3 4m11-4 3 4M7 21h10',
        'car'      => 'm5 9 2-5h10l2 5M4 9h16l1 3v6H3v-6l1-3ZM6 13h2m8 0h2M5 18v3m14-3v3',
        'activity' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm4 5-2 6-6 2 2-6 6-2Z',
        'other'    => 'M6 3h9l4 4v14H5V3h1Zm9 0v5h4M8 12h8m-8 4h5',
        'checkout'=> 'M10 3H4v18h6m4-14 5 5-5 5m-6-5h11',
        'return'  => 'M8 4 3 9l5 5M3 9h11a6 6 0 0 1 0 12h-3',
        'pin'     => 'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0ZM12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
        'arrow'   => 'M4 12h16m-6-6 6 6-6 6',
        'external'=> 'M14 3h7v7m0-7L10 14M10 3H3v18h18v-7',
        'chevron' => 'm6 9 6 6 6-6',
        'clock'   => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4v5l3 2',
        'edit'    => 'm14 5 5 5M3 21l5-1L21 7a2 2 0 0 0-5-5L3 15v6Z',
        'map'     => 'm3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2V5Zm6-2v16m6-14v16',
    ];
    ?>
    <svg class="timeline-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $paths[ $name ] ?? $paths['other'] ); ?>" /></svg>
    <?php
};
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
    $trip_direct_map_url = home_url( '/traveler/trip/' . (int) $trip_data['id'] . '/map/' );
}

if ( ! $is_static_download ) {
    $traveler->enqueue_template_assets(
        'trip',
        ! $is_readonly_timeline,
        ! $is_readonly_timeline ? 'travelerTripData' : '',
        [
            'continuousLodgingRange' => __( 'Select one continuous lodging date range.', 'traveler' ),
            'copied'                 => __( 'Copied!', 'traveler' ),
            'calendarCopied'         => __( 'Calendar subscription link copied.', 'traveler' ),
            'shareCopied'            => __( 'Share link copied.', 'traveler' ),
            'shareFailed'            => __( 'The sharing change could not be saved.', 'traveler' ),
            'copyPrompt'             => __( 'Copy this link:', 'traveler' ),
            'generating'             => __( 'Generating...', 'traveler' ),
        ]
    );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( $trip_data ? $trip_data['title'] : __( 'Travel Plan', 'traveler' ) ); ?></title>
    <?php if ( ! $is_static_download ) : ?>
        <link rel="manifest" href="<?php echo esc_url( $traveler->get_manifest_url( (int) $trip_data['id'], $share_token ) ); ?>">
        <meta name="theme-color" content="#0b6bcb">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $trip_data['title'] ?: __( 'Timeline', 'traveler' ) ); ?>">
    <?php else : ?>
        <?php $traveler->print_static_trip_styles(); ?>
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
                    <h1><span<?php echo esc_attr( App::mask_attr( 'title', (string) $trip_data['id'] ) ); ?>><?php echo esc_html( $trip_data['title'] ); ?></span></h1>
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
                        <span<?php echo esc_attr( App::mask_attr( 'person', (string) ( $trip_data['owner_id'] ?? '' ) ) ); ?>><?php echo esc_html( $traveller_label ); ?></span>
                    <?php endif; ?>
                    <?php foreach ( $traveler->get_trip_summary_parts( $trip_data, null, ! $is_static_download ) as $summary_part ) : ?>
                        <span><?php echo esc_html( $summary_part ); ?></span>
                    <?php endforeach; ?>
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of itinerary items. */
                                _n( '%d item', '%d items', count( $segments ), 'traveler' ),
                                count( $segments )
                            )
                        );
                        ?>
                    </span>
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
                                    /* translators: %s: name of the lodging being checked out of. */
                                    ? sprintf( __( 'Check out: %s', 'traveler' ), $step_title )
                                    : __( 'Check out', 'traveler' );
                            } elseif ( 'return' === $step_timeline_kind ) {
                                $step_title = '' !== $step_title
                                    /* translators: %s: name of the rental car being returned. */
                                    ? sprintf( __( 'Return car: %s', 'traveler' ), $step_title )
                                    : __( 'Return car', 'traveler' );
                            }
                            ?>
                            <span hidden data-preview-item data-url="<?php echo esc_url( '#' . $step_anchor ); ?>" data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-timeline-kind="<?php echo esc_attr( $step_timeline_kind ); ?>" data-type="<?php echo esc_attr( (string) ( $step['type'] ?? '' ) ); ?>" data-date="<?php echo esc_attr( $step_date ); ?>" data-time-label="<?php echo esc_attr( $step_time_label ); ?>" data-date-time-label="<?php echo esc_attr( $step_start_label ); ?>" data-end-date="<?php echo esc_attr( $step_effective_end_date ); ?>" data-end-time="<?php echo esc_attr( $step_end_time ); ?>" data-end-label="<?php echo esc_attr( $step_end_label ); ?>" data-location="<?php echo esc_attr( $step_location ); ?>" data-end-location="<?php echo esc_attr( $step_end_location ); ?>" data-title="<?php echo esc_attr( $step_title ); ?>"></span>
                        <?php endforeach; ?>
                        <?php foreach ( [ 'current' => __( 'Now', 'traveler' ), 'next' => __( 'Next', 'traveler' ) ] as $key => $label ) : ?>
                            <a class="mini-step <?php echo esc_attr( $key ); ?>" href="#" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-slot-label="<?php echo esc_attr( $label ); ?>" data-ended-label="<?php esc_attr_e( 'Last', 'traveler' ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'traveler' ); ?>">
                                <div class="mini-label"><?php $render_timeline_icon( 'current' === $key ? 'clock' : 'arrow' ); ?><span data-preview-label><?php echo esc_html( $label ); ?></span></div>
                                <div class="mini-title" data-preview-title<?php echo esc_attr( App::mask_attr( 'title' ) ); ?>><?php esc_html_e( 'No item', 'traveler' ); ?></div>
                                <div class="mini-countdown" data-preview-countdown></div>
                                <div class="mini-details">
                                <div class="mini-location" data-preview-meta<?php echo esc_attr( App::mask_attr( 'text' ) ); ?>></div>
                                <div class="mini-location" data-preview-location<?php echo esc_attr( App::mask_attr( 'place' ) ); ?>></div>
                                <div class="mini-location" data-preview-end<?php echo esc_attr( App::mask_attr( 'text' ) ); ?>></div>
                                </div>
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
                        $timeline_day_count_label = sprintf(
                            /* translators: %d: number of itinerary days. */
                            _n( '%d day', '%d days', count( $segments_by_day ), 'traveler' ),
                            count( $segments_by_day )
                        );
                        $timeline_item_count_label = sprintf(
                            /* translators: %d: number of itinerary items. */
                            _n( '%d item', '%d items', count( $segments ), 'traveler' ),
                            count( $segments )
                        );
                        printf(
                            /* translators: 1: formatted itinerary day count, 2: formatted trip item count. */
                            esc_html__( '%1$s · %2$s', 'traveler' ),
                            esc_html( $timeline_day_count_label ),
                            esc_html( $timeline_item_count_label )
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
                    </div>
                    <div class="timeline-header-actions">
                        <?php if ( '' !== $trip_direct_map_url ) : ?>
                            <a class="timeline-map-link" href="<?php echo esc_url( $trip_direct_map_url ); ?>" title="<?php esc_attr_e( 'Route map on OpenStreetMap', 'traveler' ); ?>">
                                <?php $render_timeline_icon( 'map' ); ?>
                                <?php esc_html_e( 'Map', 'traveler' ); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ( $is_trip_active ) : ?>
                            <button class="ghost-button timeline-now-button" type="button" data-timeline-now aria-controls="timeline" aria-label="<?php esc_attr_e( 'Jump to current time', 'traveler' ); ?>" title="<?php esc_attr_e( 'Jump to current time', 'traveler' ); ?>" disabled>
                                <?php $render_timeline_icon( 'clock' ); ?>
                                <?php esc_html_e( 'Now', 'traveler' ); ?>
                                <span class="timeline-now-time" data-timeline-clock aria-hidden="true"></span>
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
                    <template id="traveler-trip-data"><?php echo esc_html( wp_json_encode( $editable_trip_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?></template>
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
                            <div class="time-marker" aria-hidden="true"><span class="time-marker-label" hidden></span></div>
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
                                                <?php
                                                echo esc_html(
                                                    sprintf(
                                                        /* translators: %d: number of items scheduled for the day. */
                                                        _n( '%d timeline item', '%d timeline items', count( $day_segments ), 'traveler' ),
                                                        count( $day_segments )
                                                    )
                                                );
                                                ?>
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
                                            <?php $render_timeline_icon( 'chevron' ); ?>
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
                                        $timeline_icon = 'checkout';
                                    } elseif ( 'return' === $timeline_kind ) {
                                        $type_label = __( 'Return car', 'traveler' );
                                        $timeline_icon = 'return';
                                    } elseif ( 'car' === ( $segment['type'] ?? '' ) ) {
                                        $type_label = __( 'Rental car', 'traveler' );
                                        $timeline_icon = 'car';
                                    } else {
                                        $type_label = $segment_type_labels[ $segment['type'] ?? 'other' ] ?? ucfirst( $segment['type'] ?: __( 'other', 'traveler' ) );
                                        $timeline_icon = $segment['type'] ?? 'other';
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
                                                <?php if ( ! $is_end_timeline_entry && ! empty( $segment['end_time'] ) && ( '' === $segment_end_date || $segment_end_date === $segment_start_date ) ) : ?>
                                                    <span class="time timeline-end-time"><span class="screen-reader-text"><?php esc_html_e( 'Ends at', 'traveler' ); ?> </span>– <?php echo esc_html( $segment['end_time'] ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="timeline-symbol"><?php $render_timeline_icon( $timeline_icon ); ?></span>
                                            <div class="timeline-event">
                                                <div class="timeline-title-row title">
                                                    <?php if ( $is_readonly_timeline ) : ?>
                                                        <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                    <?php elseif ( ! $is_end_timeline_entry ) : ?>
                                                        <button class="timeline-title-button" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                            <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                            <?php $render_timeline_icon( 'edit' ); ?>
                                                            <span class="screen-reader-text"> — <?php esc_html_e( 'Edit item', 'traveler' ); ?></span>
                                                        </button>
                                                    <?php else : ?>
                                                        <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ( $show_url_preview && ! $has_url_preview && ! empty( $segment['url'] ) ) : ?>
                                                        <a class="timeline-url-link" href="<?php echo esc_url( (string) $segment['url'] ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open item URL', 'traveler' ); ?>">
                                                            <?php $render_timeline_icon( 'external' ); ?>
                                                            <span class="screen-reader-text"><?php esc_html_e( 'Open item URL', 'traveler' ); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="timeline-event-type"><?php echo esc_html( $type_label ); ?></span>
                                                <?php if ( '' !== $segment_end_date && $segment_end_date !== $segment_start_date ) : ?>
                                                    <div class="detail"><?php echo esc_html( $traveler->get_segment_date_range_label( $segment ) ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( $show_location && ( ! empty( $segment['location'] ) || ! empty( $segment['end_location'] ) ) ) : ?>
                                                <div class="detail timeline-route">
                                                <?php if ( ! empty( $segment['location'] ) ) : ?>
                                                    <?php $location = (string) $segment['location']; ?>
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <?php $render_timeline_icon( 'pin' ); ?>
                                                            <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ) ); ?>><?php echo esc_html( $location ); ?></span>
                                                        </a>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                    <?php $end_location = (string) $segment['end_location']; ?>
                                                        <?php $render_timeline_icon( 'arrow' ); ?>
                                                        <span class="screen-reader-text"><?php esc_html_e( 'To:', 'traveler' ); ?></span>
                                                        <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                            <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ) ); ?>><?php echo esc_html( $end_location ); ?></span>
                                                        </a>
                                                <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ( $show_private_share_details && ! empty( $segment['details'] ) ) : ?>
                                                    <div class="detail timeline-note"<?php echo esc_attr( App::mask_attr( 'text', (string) ( $segment['id'] ?? $index ) . '-details' ) ); ?>><?php echo esc_html( $segment['details'] ); ?></div>
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
                                                            <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php
                                                            echo esc_attr(
                                                                sprintf(
                                                                    /* translators: %s: attachment file name. */
                                                                    __( 'Download %s', 'traveler' ),
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
                                                <span class="title"<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
                                            <?php else : ?>
                                                <button class="timeline-title-button title" type="button" data-inline-edit-toggle aria-controls="<?php echo esc_attr( 'edit-segment-' . $index ); ?>">
                                                    <span<?php echo esc_attr( App::mask_attr( 'title', (string) ( $segment['id'] ?? $index ) . '-item' ) ); ?>><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ); ?></span>
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
                                                        <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-location' ) ); ?>><?php echo esc_html( $location ); ?></span>
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $show_location && ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                <?php $end_location = (string) $segment['end_location']; ?>
                                                <br><span class="detail">
                                                    <?php esc_html_e( 'To:', 'traveler' ); ?>
                                                    <a href="<?php echo esc_url( $get_google_maps_url( $end_location ) ); ?>" target="_blank" rel="noopener noreferrer">
                                                        <span aria-hidden="true">&#x1F4CD;</span>
                                                        <span<?php echo esc_attr( App::mask_attr( 'place', (string) ( $segment['id'] ?? $index ) . '-end-location' ) ); ?>><?php echo esc_html( $end_location ); ?></span>
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
                                                        <a class="attachment-download" href="<?php echo esc_url( (string) $attachment['url'] ); ?>" download target="_blank" rel="noopener noreferrer" title="<?php
                                                            echo esc_attr(
                                                                sprintf(
                                                                    /* translators: %s: attachment file name. */
                                                                    __( 'Download %s', 'traveler' ),
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
                                                <strong<?php echo esc_attr( App::mask_attr( 'person', (string) $editor_candidate->ID ) ); ?>><?php echo esc_html( $editor_candidate->display_name ); ?></strong>
                                                <span<?php echo esc_attr( App::mask_attr( 'email', (string) $editor_candidate->ID ) ); ?>><?php echo esc_html( $editor_candidate->user_email ); ?></span>
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
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-offline-sync data-confirm="<?php esc_attr_e( 'Delete this travel plan?', 'traveler' ); ?>">
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
    <?php if ( ! $is_static_download ) : ?>
        <?php wp_app_body_close(); ?>
    <?php endif; ?>
</body>
</html>
