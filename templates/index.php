<?php
use Traveler\App;
use Traveler\LodgingCoverage;
use Traveler\Parser\AiParser;
use Traveler\Trip;

$traveler = App::get_instance();
$trips      = array_map( static function( Trip $trip ): array {
    return $trip->to_array();
}, Trip::for_current_user() );
$imported   = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
$deleted    = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
$error      = isset( $_GET['traveler_error'] ) ? sanitize_key( wp_unslash( $_GET['traveler_error'] ) ) : '';
$shared_draft_key = isset( $_GET['shared_draft'] ) ? sanitize_key( wp_unslash( $_GET['shared_draft'] ) ) : '';
$shared_text = '' !== $shared_draft_key ? $traveler->take_share_target_text( $shared_draft_key ) : '';
$quick_plan_draft_key = isset( $_GET['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_GET['quick_plan_draft'] ) ) : '';
$quick_plan_draft = '' !== $quick_plan_draft_key ? $traveler->get_quick_plan_draft( $quick_plan_draft_key ) : [];
$quick_plan_segment = isset( $quick_plan_draft['segment'] ) && is_array( $quick_plan_draft['segment'] ) ? $quick_plan_draft['segment'] : [];
$quick_plan_matches = isset( $quick_plan_draft['matches'] ) && is_array( $quick_plan_draft['matches'] ) ? $quick_plan_draft['matches'] : [];
$has_ai     = AiParser::is_available();
$has_ai_assistant = defined( 'AI_ASSISTANT_VERSION' ) || class_exists( '\AI_Assistant' );
$delegated_owner_options = $traveler->get_delegated_trip_owner_options();
$demo_mode_enabled = $traveler->is_demo_mode_enabled();
$is_playground = $traveler->is_playground();
$all_trips_calendar_url = $is_playground ? '' : $traveler->get_user_calendar_url( get_current_user_id(), true );
$today      = current_time( 'Y-m-d' );
$segment_type_labels = [
    'flight'   => __( 'Flight', 'traveler' ),
    'lodging'  => __( 'Lodging', 'traveler' ),
    'train'    => __( 'Train', 'traveler' ),
    'car'      => __( 'Rental car', 'traveler' ),
    'activity' => __( 'Activity', 'traveler' ),
    'other'    => __( 'Other', 'traveler' ),
];
$front_demo_control_id = 'front-page-demo';
$demo_seed_trip = null;

if ( $demo_mode_enabled && ! empty( $trips ) ) {
    $demo_candidates = $trips;
    usort( $demo_candidates, static function( array $a, array $b ): int {
        return strcmp( (string) ( $a['starts_at'] ?? '' ), (string) ( $b['starts_at'] ?? '' ) );
    } );

    foreach ( $demo_candidates as $trip_data ) {
        if ( ! empty( $trip_data['starts_at'] ) && $trip_data['starts_at'] >= $today ) {
            $demo_seed_trip = $trip_data;
            break;
        }
    }

    $demo_seed_trip = $demo_seed_trip ?: $demo_candidates[0];
}

$front_demo_control_value = $demo_seed_trip ? ( ( $demo_seed_trip['starts_at'] ?: $today ) . 'T12:00' ) : ( $today . 'T12:00' );
if ( $demo_mode_enabled ) {
    $today = substr( $front_demo_control_value, 0, 10 );
}

$current_trips = [];
$upcoming_trips = [];
$past_trips = [];

foreach ( $trips as $trip_data ) {
    $starts = (string) ( $trip_data['starts_at'] ?? '' );
    $ends   = (string) ( $trip_data['ends_at'] ?? '' );

    if ( $starts && $ends && $starts <= $today && $ends >= $today ) {
        $current_trips[] = $trip_data;
    } elseif ( $starts && $starts > $today ) {
        $upcoming_trips[] = $trip_data;
    } elseif ( $ends && $ends < $today ) {
        $past_trips[] = $trip_data;
    } else {
        $upcoming_trips[] = $trip_data;
    }
}

$sort_asc = static function( array $a, array $b ): int {
    return strcmp( (string) ( $a['starts_at'] ?? '' ), (string) ( $b['starts_at'] ?? '' ) );
};
$sort_desc = static function( array $a, array $b ): int {
    return strcmp( (string) ( $b['ends_at'] ?? '' ), (string) ( $a['ends_at'] ?? '' ) );
};

usort( $current_trips, $sort_asc );
usort( $upcoming_trips, $sort_asc );
usort( $past_trips, $sort_desc );

$quick_plan_selectable_trips = array_values( array_merge( $current_trips, $upcoming_trips ) );

$past_trips_by_year = [];
foreach ( $past_trips as $trip_data ) {
    $year = substr( (string) ( ( $trip_data['ends_at'] ?? '' ) ?: ( $trip_data['starts_at'] ?? '' ) ), 0, 4 );
    $year = preg_match( '/^\d{4}$/', $year ) ? $year : __( 'Earlier', 'traveler' );
    $past_trips_by_year[ $year ][] = $trip_data;
}

$featured_trip = $current_trips[0] ?? ( $demo_mode_enabled ? ( $upcoming_trips[0] ?? $past_trips[0] ?? null ) : null );

$get_trip_url = static function( array $trip_data ): string {
    return home_url( '/traveler/trip/' . absint( $trip_data['id'] ?? 0 ) . '/' );
};

$get_timeline_preview = static function( array $trip_data ) use ( $today ): array {
    $segments = isset( $trip_data['segments'] ) && is_array( $trip_data['segments'] ) ? $trip_data['segments'] : [];
    usort( $segments, static function( array $a, array $b ): int {
        return strcmp(
            trim( (string) ( $a['date'] ?? '' ) . ' ' . (string) ( $a['time'] ?? '' ) ),
            trim( (string) ( $b['date'] ?? '' ) . ' ' . (string) ( $b['time'] ?? '' ) )
        );
    } );

    $current = null;
    $next = null;
    foreach ( $segments as $segment ) {
        $date = (string) ( $segment['date'] ?? '' );
        if ( $date && $date <= $today ) {
            $current = $segment;
            continue;
        }
        if ( $date && $date >= $today ) {
            $next = $segment;
            break;
        }
    }

    if ( ! $current && $segments ) {
        $current = $segments[0];
    }
    if ( ! $next && $segments ) {
        foreach ( $segments as $segment ) {
            if ( $segment !== $current ) {
                $next = $segment;
                break;
            }
        }
    }

    return [
        'current' => $current,
        'next'    => $next,
    ];
};
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( __( 'Traveler', 'traveler' ) ); ?></title>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <?php wp_app_head(); ?>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.5;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
        }
        main { max-width: 1120px; margin: 0 auto; padding: 32px 18px 56px; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2rem, 5vw, 3.5rem); line-height: 1.04; margin-bottom: 12px; letter-spacing: 0; }
        h2 { font-size: 1.05rem; margin-bottom: 12px; }
        h3 { font-size: 1rem; margin-bottom: 6px; }
        a { color: var(--wp-app-color-link); }
        .screen-reader-text {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .app-header { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: end; margin-bottom: 22px; }
        .lede { max-width: 680px; color: var(--wp-app-color-muted); font-size: 1.02rem; margin-bottom: 0; }
        .status-stack { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .status {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 4px 10px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 999px;
            background: var(--wp-app-color-surface);
            color: var(--wp-app-color-muted);
            font-size: 0.82rem;
            white-space: nowrap;
        }
        .status.available { color: #0f6b42; border-color: rgba(15, 107, 66, 0.32); background: rgba(15, 107, 66, 0.08); }
        .status.unavailable { color: #8a4b08; border-color: rgba(138, 75, 8, 0.28); background: rgba(138, 75, 8, 0.08); }
        .notice {
            margin-bottom: 18px;
            border-radius: 6px;
            padding: 12px 14px;
            border: 1px solid rgba(15, 107, 66, 0.32);
            background: rgba(15, 107, 66, 0.08);
        }
        .notice.error { border-color: rgba(138, 75, 8, 0.28); background: rgba(138, 75, 8, 0.08); }
        .dashboard { display: grid; grid-template-columns: minmax(0, 1.35fr) 340px; gap: 22px; align-items: start; }
        .panel {
            background: var(--wp-app-color-surface);
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 18px;
        }
        .import-panel input,
        .import-panel select,
        .import-panel textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            padding: 10px;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
            font: inherit;
        }
        .import-panel textarea {
            min-height: 118px;
            resize: vertical;
        }
        .entry-mode-control {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .entry-mode-control > input[type="radio"] {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            clip-path: inset(50%);
            white-space: nowrap;
        }
        .entry-mode-label {
            display: grid;
            gap: 2px;
            min-height: 58px;
            box-sizing: border-box;
            margin: 0;
            padding: 10px 12px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-background);
            cursor: pointer;
        }
        .entry-mode-label span { color: var(--wp-app-color-muted); font-size: 0.82rem; font-weight: 400; }
        .entry-mode-control > input[type="radio"]:checked + .entry-mode-label {
            border-color: var(--wp-app-color-link);
            box-shadow: inset 0 0 0 1px var(--wp-app-color-link);
        }
        .entry-mode-control > input[type="radio"]:focus-visible + .entry-mode-label {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .entry-mode-owner,
        .entry-mode-panel { grid-column: 1 / -1; }
        .entry-mode-owner { margin-top: 6px; }
        .entry-mode-panel {
            display: none;
            padding-top: 8px;
        }
        #entry_mode_create:checked ~ .entry-mode-create,
        #entry_mode_import:checked ~ .entry-mode-import { display: block; }
        .entry-mode-panel button { width: 100%; }
        .quick-plan-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .quick-plan-fields .field-wide { grid-column: 1 / -1; }
        .quick-plan-match-list { display: grid; gap: 8px; margin: 4px 0; }
        .quick-plan-choice {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            padding: 10px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-background);
            font-weight: 400;
        }
        .quick-plan-choice input[type="radio"] { width: auto; margin-top: 4px; }
        .quick-plan-choice input[type="text"] { margin-top: 6px; }
        .quick-plan-choice strong { display: block; overflow-wrap: anywhere; }
        .quick-plan-confirm { color: var(--wp-app-color-muted); font-size: 0.9rem; }
        .quick-plan-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
        .calendar-subscription {
            display: grid;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--wp-app-color-border);
        }
        .calendar-subscription h3 { margin-bottom: 0; }
        .calendar-button {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            padding: 9px 12px;
            background: transparent;
            color: var(--wp-app-color-text);
            font: inherit;
            font-weight: 700;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
        }
        label { display: block; font-weight: 650; margin-bottom: 7px; }
        .drop-zone {
            display: grid;
            gap: 4px;
            margin-bottom: 10px;
            border: 1px dashed var(--wp-app-color-border);
            border-radius: 8px;
            padding: 12px;
            background: var(--wp-app-color-background);
            cursor: pointer;
        }
        .drop-zone.dragging {
            border-color: var(--wp-app-color-link);
            background: var(--wp-app-color-surface-alt);
        }
        .drop-zone input { position: absolute; opacity: 0; pointer-events: none; }
        .drop-title { font-weight: 700; }
        .drop-file-name, .hint { color: var(--wp-app-color-muted); font-size: 0.88rem; overflow-wrap: anywhere; }
        .demo-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin: 14px 0; }
        .demo-controls label { min-width: 190px; margin: 0; }
        button {
            appearance: none;
            border: 0;
            border-radius: 6px;
            background: var(--wp-app-color-link);
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 9px 12px;
            cursor: pointer;
            min-height: 38px;
            white-space: nowrap;
        }
        .ghost-button {
            background: transparent;
            color: var(--wp-app-color-text);
            border: 1px solid var(--wp-app-color-border);
        }
        .trip-list { display: grid; gap: 10px; }
        .trip-card {
            display: block;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 14px;
            background: var(--wp-app-color-background);
            color: inherit;
            text-decoration: none;
        }
        .trip-card:hover,
        .trip-card:focus,
        .trip-card:focus-visible,
        .trip-card:hover *,
        .trip-card:focus *,
        .trip-card:focus-visible * {
            text-decoration: none;
        }
        .trip-card:hover {
            border-color: var(--wp-app-color-link);
            background: var(--wp-app-color-surface);
        }
        .trip-card:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .trip-card.highlight { outline: 2px solid var(--wp-app-color-link); outline-offset: 2px; }
        .trip-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: var(--wp-app-color-muted); font-size: 0.88rem; }
        .current-card {
            background: var(--wp-app-color-background);
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 16px;
        }
        .current-card h3 a {
            color: inherit;
            text-decoration: none;
        }
        .current-card h3 a:hover,
        .current-card h3 a:focus,
        .current-card h3 a:focus-visible {
            color: var(--wp-app-color-link);
            text-decoration: underline;
        }
        .mini-timeline {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 14px 0;
        }
        .mini-step {
            display: block;
            border-left: 3px solid var(--wp-app-color-border);
            padding-left: 12px;
            color: inherit;
            text-decoration: none;
        }
        .mini-step:hover,
        .mini-step:focus,
        .mini-step:focus-visible,
        .mini-step:hover *,
        .mini-step:focus *,
        .mini-step:focus-visible * {
            text-decoration: none;
        }
        .mini-step:hover { border-left-color: var(--wp-app-color-link); }
        .mini-step:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 3px;
        }
        .mini-step.current { border-left-color: var(--wp-app-color-link); }
        .mini-label { color: var(--wp-app-color-muted); font-size: 0.78rem; text-transform: uppercase; }
        .mini-title { font-weight: 750; overflow-wrap: anywhere; }
        .mini-location {
            color: var(--wp-app-color-muted);
            overflow-wrap: anywhere;
            font-size: 0.88rem;
            line-height: 1.42;
        }
        .mini-countdown {
            color: var(--wp-app-color-muted);
            font-size: 0.82rem;
            font-weight: 650;
            margin-top: 2px;
        }
        .mini-step[hidden] { display: none; }
        .section-title { display: flex; align-items: baseline; gap: 12px; }
        .empty {
            min-height: 130px;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--wp-app-color-muted);
            border: 1px dashed var(--wp-app-color-border);
            border-radius: 8px;
            padding: 18px;
        }
        @media (max-width: 880px) {
            .app-header, .dashboard, .mini-timeline, .quick-plan-fields { grid-template-columns: 1fr; }
            .status-stack { justify-content: flex-start; }
            .dashboard-import-confirm .trip-sections { order: 2; }
            .dashboard-import-confirm .import-panel { order: 1; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main>
        <header class="app-header">
            <div>
                <h1><?php esc_html_e( 'Traveler', 'traveler' ); ?></h1>
                <p class="lede"><?php esc_html_e( 'A private travel organizer for WordPress: turn booking confirmations into itineraries, follow them on a day-by-day timeline, and keep a travel journal.', 'traveler' ); ?></p>
            </div>
            <div class="status-stack" aria-label="<?php esc_attr_e( 'Integration status', 'traveler' ); ?>">
                <span class="status <?php echo $has_ai ? 'available' : 'unavailable'; ?>">
                    <?php echo esc_html( $has_ai ? __( 'WordPress AI parser available', 'traveler' ) : __( 'Fallback parser active', 'traveler' ) ); ?>
                </span>
                <span class="status <?php echo $has_ai_assistant ? 'available' : 'unavailable'; ?>">
                    <?php echo esc_html( $has_ai_assistant ? __( 'AI Assistant connected', 'traveler' ) : __( 'AI Assistant not detected', 'traveler' ) ); ?>
                </span>
            </div>
        </header>

        <?php if ( $imported ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan imported.', 'traveler' ); ?></div>
        <?php elseif ( $deleted ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan deleted.', 'traveler' ); ?></div>
        <?php elseif ( isset( $_GET['settings_updated'] ) ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Settings saved.', 'traveler' ); ?></div>
        <?php elseif ( $error ) : ?>
            <div class="notice error" role="alert"><?php echo esc_html( $traveler->get_error_notice_message( $error, __( 'The itinerary could not be imported.', 'traveler' ) ) ); ?></div>
        <?php endif; ?>

        <?php if ( $demo_mode_enabled && ! empty( $trips ) ) : ?>
            <?php
            $demo_control_id = $front_demo_control_id;
            $demo_control_value = $front_demo_control_value;
            require __DIR__ . '/partials/demo-controls.php';
            ?>
        <?php endif; ?>

        <div class="dashboard <?php echo ! empty( $quick_plan_segment ) ? 'dashboard-import-confirm' : ''; ?>">
            <div class="trip-sections">
                <?php if ( empty( $trips ) ) : ?>
                    <section class="panel">
                        <div class="empty"><?php esc_html_e( 'No travel plans yet. Import a confirmation or calendar file to build your first itinerary.', 'traveler' ); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ( $featured_trip ) : ?>
                    <section class="panel" aria-labelledby="current-trip-heading" data-ai-assistant-important>
                        <div class="section-title">
                            <h2 id="current-trip-heading"><?php echo esc_html( ! empty( $current_trips ) ? __( 'Current Trip', 'traveler' ) : __( 'Trip Preview', 'traveler' ) ); ?></h2>
                        </div>
                        <?php $current_trip = $featured_trip; ?>
                        <?php $current_trip_timeline_segments = LodgingCoverage::timeline_segments( $current_trip['segments'] ?? [] ); ?>
                        <article class="current-card">
                            <h3><a href="<?php echo esc_url( $get_trip_url( $current_trip ) ); ?>#timeline-heading"><span<?php echo App::mask_attr( 'title', (string) ( $current_trip['id'] ?? '' ) ); ?>><?php echo esc_html( $current_trip['title'] ); ?></span></a></h3>
                            <div class="trip-meta">
                                <?php $current_trip_owner_label = $traveler->get_trip_traveller_label( $current_trip ); ?>
                                <?php if ( '' !== $current_trip_owner_label ) : ?>
                                    <span<?php echo App::mask_attr( 'person', (string) ( $current_trip['owner_id'] ?? '' ) ); ?>><?php echo esc_html( $current_trip_owner_label ); ?></span>
                                <?php endif; ?>
                                <?php foreach ( $traveler->get_trip_summary_parts( $current_trip, $today ) as $summary_part ) : ?>
                                    <span><?php echo esc_html( $summary_part ); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="mini-timeline" data-demo-target="<?php echo esc_attr( $front_demo_control_id ); ?>" data-demo-preview>
                                <?php foreach ( $current_trip_timeline_segments as $step ) : ?>
                                    <?php
                                    $step_timeline_kind = (string) ( $step['_timeline_kind'] ?? 'start' );
                                    $step_anchor_suffix = in_array( $step_timeline_kind, [ 'checkout', 'return' ], true ) ? '-' . $step_timeline_kind : '';
                                    $step_anchor = 'segment-' . (int) ( $step['_index'] ?? ( $step['id'] ?? 0 ) ) . $step_anchor_suffix;
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
                                    <span hidden data-preview-item data-url="<?php echo esc_url( home_url( '/traveler/trip/' . $current_trip['id'] . '/#' . $step_anchor ) ); ?>" data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-timeline-kind="<?php echo esc_attr( $step_timeline_kind ); ?>" data-type="<?php echo esc_attr( (string) ( $step['type'] ?? '' ) ); ?>" data-date="<?php echo esc_attr( $step_date ); ?>" data-time-label="<?php echo esc_attr( $step_time_label ); ?>" data-date-time-label="<?php echo esc_attr( $step_start_label ); ?>" data-end-date="<?php echo esc_attr( $step_effective_end_date ); ?>" data-end-time="<?php echo esc_attr( $step_end_time ); ?>" data-end-label="<?php echo esc_attr( $step_end_label ); ?>" data-location="<?php echo esc_attr( (string) ( $step['location'] ?? '' ) ); ?>" data-end-location="<?php echo esc_attr( (string) ( $step['end_location'] ?? '' ) ); ?>" data-title="<?php echo esc_attr( $step_title ); ?>"></span>
                                <?php endforeach; ?>
                                <?php foreach ( [ 'current' => __( 'Current', 'traveler' ), 'next' => __( 'Next', 'traveler' ) ] as $key => $label ) : ?>
                                    <a class="mini-step <?php echo esc_attr( $key ); ?>" href="#" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'traveler' ); ?>">
                                        <div class="mini-label"><?php echo esc_html( $label ); ?></div>
                                        <div class="mini-title" data-preview-title<?php echo App::mask_attr( 'title' ); ?>><?php esc_html_e( 'No item', 'traveler' ); ?></div>
                                        <div class="mini-countdown" data-preview-countdown></div>
                                        <div class="mini-location" data-preview-meta<?php echo App::mask_attr( 'text' ); ?>></div>
                                        <div class="mini-location" data-preview-location<?php echo App::mask_attr( 'place' ); ?>></div>
                                        <div class="mini-location" data-preview-end<?php echo App::mask_attr( 'text' ); ?>></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ( ! empty( $upcoming_trips ) ) : ?>
                    <section class="panel" aria-labelledby="upcoming-heading">
                        <div class="section-title">
                            <h2 id="upcoming-heading"><?php esc_html_e( 'Upcoming Trips', 'traveler' ); ?></h2>
                        </div>
                        <div class="trip-list">
                            <?php foreach ( $upcoming_trips as $trip_data ) : ?>
                                <a class="trip-card <?php echo (int) $trip_data['id'] === $imported ? 'highlight' : ''; ?>" href="<?php echo esc_url( $get_trip_url( $trip_data ) ); ?>">
                                    <h3><span<?php echo App::mask_attr( 'title', (string) ( $trip_data['id'] ?? '' ) ); ?>><?php echo esc_html( $trip_data['title'] ); ?></span></h3>
                                    <div class="trip-meta">
                                        <?php $trip_owner_label = $traveler->get_trip_traveller_label( $trip_data ); ?>
                                        <?php if ( '' !== $trip_owner_label ) : ?>
                                            <span<?php echo App::mask_attr( 'person', (string) ( $trip_data['owner_id'] ?? '' ) ); ?>><?php echo esc_html( $trip_owner_label ); ?></span>
                                        <?php endif; ?>
                                        <?php foreach ( $traveler->get_trip_summary_parts( $trip_data, $today ) as $summary_part ) : ?>
                                            <span><?php echo esc_html( $summary_part ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php foreach ( $past_trips_by_year as $year => $year_trips ) : ?>
                    <section class="panel" aria-labelledby="past-<?php echo esc_attr( sanitize_key( (string) $year ) ); ?>-heading">
                        <div class="section-title">
                            <h2 id="past-<?php echo esc_attr( sanitize_key( (string) $year ) ); ?>-heading"><?php echo esc_html( $year ); ?></h2>
                        </div>
                        <div class="trip-list">
                            <?php foreach ( $year_trips as $trip_data ) : ?>
                                <a class="trip-card" href="<?php echo esc_url( $get_trip_url( $trip_data ) ); ?>">
                                    <h3><span<?php echo App::mask_attr( 'title', (string) ( $trip_data['id'] ?? '' ) ); ?>><?php echo esc_html( $trip_data['title'] ); ?></span></h3>
                                    <div class="trip-meta">
                                        <?php $trip_owner_label = $traveler->get_trip_traveller_label( $trip_data ); ?>
                                        <?php if ( '' !== $trip_owner_label ) : ?>
                                            <span<?php echo App::mask_attr( 'person', (string) ( $trip_data['owner_id'] ?? '' ) ); ?>><?php echo esc_html( $trip_owner_label ); ?></span>
                                        <?php endif; ?>
                                        <?php foreach ( $traveler->get_trip_summary_parts( $trip_data, $today ) as $summary_part ) : ?>
                                            <span><?php echo esc_html( $summary_part ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <aside class="panel import-panel" aria-labelledby="import-trip-heading">
                <h2 id="import-trip-heading"><?php esc_html_e( 'Add a trip', 'traveler' ); ?></h2>
                    <?php if ( ! empty( $quick_plan_segment ) ) : ?>
                        <?php
                        $quick_plan_trip_title = isset( $quick_plan_draft['trip_title'] )
                            ? (string) $quick_plan_draft['trip_title']
                            : ( ! empty( $quick_plan_segment['location'] ) ? (string) $quick_plan_segment['location'] : __( 'Quick Travel Plan', 'traveler' ) );
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
                        ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="traveler_import">
                            <input type="hidden" name="quick_plan_draft" value="<?php echo esc_attr( $quick_plan_draft_key ); ?>">
                            <?php wp_nonce_field( 'traveler_import' ); ?>
                            <p class="quick-plan-confirm">
                                <?php esc_html_e( 'Review the parsed entry fields, then choose whether to add it to an existing trip or create a new trip.', 'traveler' ); ?>
                                <?php
                                printf(
                                    /* translators: %s: parser source label. */
                                    esc_html__( ' Parsed with: %s.', 'traveler' ),
                                    esc_html( $quick_plan_parser_label )
                                );
                                ?>
                                <?php if ( '' !== $quick_plan_parser_error_code || '' !== $quick_plan_parser_error_message ) : ?>
                                    <?php
                                    printf(
                                        /* translators: 1: parser error code, 2: parser error message. */
                                        esc_html__( ' AI parser error: %1$s %2$s', 'traveler' ),
                                        esc_html( $quick_plan_parser_error_code ),
                                        esc_html( $quick_plan_parser_error_message )
                                    );
                                    ?>
                                <?php endif; ?>
                            </p>
                            <div class="quick-plan-fields">
                                <label class="field-wide">
                                    <?php esc_html_e( 'Title', 'traveler' ); ?>
                                    <input name="segment_title" value="<?php echo esc_attr( (string) ( $quick_plan_segment['title'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Type', 'traveler' ); ?>
                                    <select name="segment_type">
                                        <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $quick_plan_segment['type'] ?? 'activity', $type ); ?>><?php echo esc_html( $segment_type_labels[ $type ] ?? ucfirst( $type ) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <?php esc_html_e( 'Location', 'traveler' ); ?>
                                    <input name="segment_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['location'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Date', 'traveler' ); ?>
                                    <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Time', 'traveler' ); ?>
                                    <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['time'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Date', 'traveler' ); ?>
                                    <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Time', 'traveler' ); ?>
                                    <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_time'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'End Location', 'traveler' ); ?>
                                    <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_location'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'URL', 'traveler' ); ?>
                                    <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $quick_plan_segment['url'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'Details', 'traveler' ); ?>
                                    <textarea name="segment_details"><?php echo esc_textarea( (string) ( $quick_plan_segment['details'] ?? '' ) ); ?></textarea>
                                </label>
                            </div>
                            <div class="quick-plan-match-list">
                                <?php if ( ! empty( $quick_plan_matches ) ) : ?>
                                    <?php foreach ( $quick_plan_matches as $index => $match ) : ?>
                                        <label class="quick-plan-choice">
                                            <input type="radio" name="quick_plan_target" value="<?php echo esc_attr( (string) ( $match['id'] ?? 0 ) ); ?>" <?php checked( 0, $index ); ?>>
                                            <span>
                                                <strong><?php echo esc_html( (string) ( $match['title'] ?? __( 'Travel plan', 'traveler' ) ) ); ?></strong>
                                                <?php echo esc_html( $traveler->format_date_range_label( (string) ( $match['starts_at'] ?? '' ), (string) ( $match['ends_at'] ?? '' ) ) ); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="quick-plan-confirm"><?php esc_html_e( 'No matching existing travel plan was found for these fields.', 'traveler' ); ?></p>
                                <?php endif; ?>
                                <label class="quick-plan-choice">
                                    <input type="radio" name="quick_plan_target" value="new" <?php checked( empty( $quick_plan_matches ) ); ?>>
                                    <span>
                                        <strong><?php esc_html_e( 'Create a new travel plan', 'traveler' ); ?></strong>
                                        <?php esc_html_e( 'Use this item as the first entry.', 'traveler' ); ?>
                                        <input type="text" name="quick_plan_trip_title" value="<?php echo esc_attr( $quick_plan_trip_title ); ?>" aria-label="<?php esc_attr_e( 'New travel plan title', 'traveler' ); ?>">
                                        <?php if ( count( $delegated_owner_options ) > 1 ) : ?>
                                            <select name="traveler_owner_user_id" aria-label="<?php esc_attr_e( 'Create travel plan for', 'traveler' ); ?>">
                                                <?php foreach ( $delegated_owner_options as $owner_option ) : ?>
                                                    <option value="<?php echo esc_attr( (string) $owner_option->ID ); ?>">
                                                        <?php echo esc_html( get_current_user_id() === (int) $owner_option->ID ? __( 'Myself', 'traveler' ) : $owner_option->display_name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php if ( count( $quick_plan_selectable_trips ) > 1 ) : ?>
                                    <label class="quick-plan-choice">
                                        <input type="radio" name="quick_plan_target" value="existing">
                                        <span>
                                            <strong><?php esc_html_e( 'Choose a current or upcoming trip', 'traveler' ); ?></strong>
                                            <select name="quick_plan_existing_trip" data-quick-plan-existing-trip aria-label="<?php esc_attr_e( 'Current or upcoming trip', 'traveler' ); ?>">
                                                <?php foreach ( $quick_plan_selectable_trips as $trip_data ) : ?>
                                                    <option value="<?php echo esc_attr( (string) ( $trip_data['id'] ?? 0 ) ); ?>">
                                                        <?php
                                                        echo esc_html(
                                                            trim(
                                                                (string) ( $trip_data['title'] ?? __( 'Travel plan', 'traveler' ) ) . ' - ' .
                                                                $traveler->format_date_range_label( (string) ( $trip_data['starts_at'] ?? '' ), (string) ( $trip_data['ends_at'] ?? '' ) )
                                                            )
                                                        );
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            </div>
                            <div class="quick-plan-actions">
                                <button type="submit"><?php esc_html_e( 'Add Plan', 'traveler' ); ?></button>
                            </div>
                        </form>
                    <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="traveler_import">
                    <?php wp_nonce_field( 'traveler_import' ); ?>
                    <fieldset class="entry-mode-control">
                        <legend class="screen-reader-text"><?php esc_html_e( 'Choose how to add a trip', 'traveler' ); ?></legend>
                        <input type="radio" id="entry_mode_create" name="traveler_entry_mode" value="create" <?php checked( '', $shared_text ); ?>>
                        <label class="entry-mode-label" for="entry_mode_create">
                            <?php esc_html_e( 'New trip', 'traveler' ); ?>
                            <span><?php esc_html_e( 'Start with a name', 'traveler' ); ?></span>
                        </label>
                        <input type="radio" id="entry_mode_import" name="traveler_entry_mode" value="import" <?php checked( '' !== $shared_text ); ?>>
                        <label class="entry-mode-label" for="entry_mode_import">
                            <?php esc_html_e( 'Import confirmation', 'traveler' ); ?>
                            <span><?php esc_html_e( 'Paste text or add a file', 'traveler' ); ?></span>
                        </label>
                        <?php if ( count( $delegated_owner_options ) > 1 ) : ?>
                            <label class="entry-mode-owner" for="traveler_owner_user_id">
                                <?php esc_html_e( 'Create for', 'traveler' ); ?>
                                <select id="traveler_owner_user_id" name="traveler_owner_user_id">
                                    <?php foreach ( $delegated_owner_options as $owner_option ) : ?>
                                        <option value="<?php echo esc_attr( (string) $owner_option->ID ); ?>">
                                            <?php echo esc_html( get_current_user_id() === (int) $owner_option->ID ? __( 'Myself', 'traveler' ) : $owner_option->display_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <div class="entry-mode-panel entry-mode-create">
                            <label for="trip_name"><?php esc_html_e( 'Trip name', 'traveler' ); ?></label>
                            <input type="text" id="trip_name" name="trip_name" placeholder="<?php esc_attr_e( 'Example: Summer in Portugal', 'traveler' ); ?>">
                            <p class="hint"><?php esc_html_e( 'Create an empty trip now, then add dates and itinerary items.', 'traveler' ); ?></p>
                            <button type="submit"><?php esc_html_e( 'Create trip', 'traveler' ); ?></button>
                        </div>
                        <div class="entry-mode-panel entry-mode-import">
                            <label class="drop-zone" id="itinerary_drop_zone" for="itinerary_file">
                                <span class="drop-title"><?php esc_html_e( 'Drop file', 'traveler' ); ?></span>
                                <span class="drop-file-name" id="itinerary_file_name"><?php esc_html_e( 'ICS or text file', 'traveler' ); ?></span>
                                <input type="file" id="itinerary_file" name="itinerary_file" accept=".ics,.txt,text/calendar,text/plain">
                            </label>
                            <label for="itinerary_text"><?php esc_html_e( 'Confirmation or itinerary text', 'traveler' ); ?></label>
                            <textarea id="itinerary_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Example: Dinner in Hamburg on August 2 at 7pm...', 'traveler' ); ?>"<?php echo '' !== $shared_text ? ' autofocus' : ''; ?>><?php echo esc_textarea( $shared_text ); ?></textarea>
                            <p class="hint"><?php echo esc_html( $has_ai ? __( 'Traveler will extract the details for you to review before saving.', 'traveler' ) : __( 'Traveler will use calendar, quick, or basic parsing and ask you to review the result.', 'traveler' ) ); ?></p>
                            <button type="submit"><?php esc_html_e( 'Review import', 'traveler' ); ?></button>
                        </div>
                    </fieldset>
                </form>
                <?php if ( '' !== $all_trips_calendar_url && ! $is_playground ) : ?>
                    <div class="calendar-subscription" data-calendar-subscription>
                        <h3><?php esc_html_e( 'Calendar Subscription', 'traveler' ); ?></h3>
                        <p class="hint"><?php esc_html_e( 'Add this URL to your calendar app to see all your trips there.', 'traveler' ); ?></p>
                        <button class="calendar-button" type="button" data-copy-url="<?php echo esc_attr( $all_trips_calendar_url ); ?>"><?php esc_html_e( 'Copy URL', 'traveler' ); ?></button>
                        <p class="hint" data-copy-status aria-live="polite"></p>
                    </div>
                <?php endif; ?>
                    <?php endif; ?>
            </aside>
        </div>
    </main>

    <?php wp_app_body_close(); ?>
    <script>
        (function() {
            var tripSelect = document.querySelector('[data-quick-plan-existing-trip]');
            if (!tripSelect) {
                return;
            }

            function selectExistingTripOption() {
                var choice = tripSelect.closest('.quick-plan-choice');
                var radio = choice ? choice.querySelector('input[type="radio"]') : null;
                if (radio) {
                    radio.checked = true;
                }
            }

            tripSelect.addEventListener('focus', selectExistingTripOption);
            tripSelect.addEventListener('change', selectExistingTripOption);
        }());

        (function() {
            var dropZone = document.getElementById('itinerary_drop_zone');
            var fileInput = document.getElementById('itinerary_file');
            var fileName = document.getElementById('itinerary_file_name');

            if (!dropZone || !fileInput || !fileName) {
                return;
            }

            function showFileName() {
                fileName.textContent = fileInput.files && fileInput.files.length
                    ? fileInput.files[0].name
                    : '<?php echo esc_js( __( 'ICS or text file', 'traveler' ) ); ?>';
            }

            ['dragenter', 'dragover'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function(event) {
                    event.preventDefault();
                    dropZone.classList.add('dragging');
                });
            });

            ['dragleave', 'drop'].forEach(function(eventName) {
                dropZone.addEventListener(eventName, function(event) {
                    event.preventDefault();
                    dropZone.classList.remove('dragging');
                });
            });

            dropZone.addEventListener('drop', function(event) {
                if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                    fileInput.files = event.dataTransfer.files;
                    showFileName();
                }
            });

            fileInput.addEventListener('change', showFileName);
        }());

        (function() {
            var control = document.querySelector('[data-calendar-subscription]');
            if (!control) {
                return;
            }

            var button = control.querySelector('[data-copy-url]');
            var status = control.querySelector('[data-copy-status]');
            if (!button) {
                return;
            }

            function confirmCopied() {
                button.textContent = '<?php echo esc_js( __( 'Copied!', 'traveler' ) ); ?>';
                if (status) {
                    status.textContent = '<?php echo esc_js( __( 'Calendar subscription link copied.', 'traveler' ) ); ?>';
                }
                window.setTimeout(function() {
                    button.textContent = '<?php echo esc_js( __( 'Copy URL', 'traveler' ) ); ?>';
                }, 1800);
            }

            button.addEventListener('click', function() {
                var url = button.getAttribute('data-copy-url') || '';
                if (!url) {
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(confirmCopied).catch(function() {
                        window.prompt('<?php echo esc_js( __( 'Copy this link:', 'traveler' ) ); ?>', url);
                        confirmCopied();
                    });
                    return;
                }

                window.prompt('<?php echo esc_js( __( 'Copy this link:', 'traveler' ) ); ?>', url);
                confirmCopied();
            });
        }());
    </script>
</body>
</html>
