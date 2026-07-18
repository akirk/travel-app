<?php
use TravelApp\App;

$travel_app = App::get_instance();
$trips      = array_map( [ $travel_app, 'format_trip_for_output' ], $travel_app->get_user_trips() );
$imported   = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
$deleted    = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
$error      = isset( $_GET['travel_app_error'] ) ? sanitize_key( wp_unslash( $_GET['travel_app_error'] ) ) : '';
$quick_plan_draft_key = isset( $_GET['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_GET['quick_plan_draft'] ) ) : '';
$quick_plan_draft = '' !== $quick_plan_draft_key ? $travel_app->get_quick_plan_draft( $quick_plan_draft_key ) : [];
$quick_plan_segment = isset( $quick_plan_draft['segment'] ) && is_array( $quick_plan_draft['segment'] ) ? $quick_plan_draft['segment'] : [];
$quick_plan_matches = isset( $quick_plan_draft['matches'] ) && is_array( $quick_plan_draft['matches'] ) ? $quick_plan_draft['matches'] : [];
$has_ai     = function_exists( 'wp_ai_client_prompt' );
$has_ai_assistant = defined( 'AI_ASSISTANT_VERSION' ) || class_exists( '\AI_Assistant' );
$demo_mode_enabled = $travel_app->is_demo_mode_enabled();
$today      = current_time( 'Y-m-d' );
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

$past_trips_by_year = [];
foreach ( $past_trips as $trip_data ) {
    $year = substr( (string) ( ( $trip_data['ends_at'] ?? '' ) ?: ( $trip_data['starts_at'] ?? '' ) ), 0, 4 );
    $year = preg_match( '/^\d{4}$/', $year ) ? $year : __( 'Earlier', 'travel-app' );
    $past_trips_by_year[ $year ][] = $trip_data;
}

$featured_trip = $current_trips[0] ?? ( $demo_mode_enabled ? ( $upcoming_trips[0] ?? $past_trips[0] ?? null ) : null );

$get_trip_url = static function( array $trip_data ): string {
    return home_url( '/travel-app/trip/' . absint( $trip_data['id'] ?? 0 ) . '/' );
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
    <title><?php echo wp_app_title( __( 'Travel App', 'travel-app' ) ); ?></title>
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
            button { width: 100%; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main>
        <header class="app-header">
            <div>
                <h1><?php esc_html_e( 'Travel App', 'travel-app' ); ?></h1>
                <p class="lede"><?php esc_html_e( 'A private travel organizer for WordPress.', 'travel-app' ); ?></p>
            </div>
            <div class="status-stack" aria-label="<?php esc_attr_e( 'Integration status', 'travel-app' ); ?>">
                <span class="status <?php echo $has_ai ? 'available' : 'unavailable'; ?>">
                    <?php echo esc_html( $has_ai ? __( 'WordPress AI parser available', 'travel-app' ) : __( 'Fallback parser active', 'travel-app' ) ); ?>
                </span>
                <span class="status <?php echo $has_ai_assistant ? 'available' : 'unavailable'; ?>">
                    <?php echo esc_html( $has_ai_assistant ? __( 'AI Assistant connected', 'travel-app' ) : __( 'AI Assistant not detected', 'travel-app' ) ); ?>
                </span>
            </div>
        </header>

        <?php if ( $imported ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan imported.', 'travel-app' ); ?></div>
        <?php elseif ( $deleted ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan deleted.', 'travel-app' ); ?></div>
        <?php elseif ( $error ) : ?>
            <div class="notice error" role="alert"><?php esc_html_e( 'The itinerary could not be imported.', 'travel-app' ); ?></div>
        <?php endif; ?>

        <?php if ( $demo_mode_enabled && ! empty( $trips ) ) : ?>
            <?php
            $demo_control_id = $front_demo_control_id;
            $demo_control_value = $front_demo_control_value;
            require __DIR__ . '/partials/demo-controls.php';
            ?>
        <?php endif; ?>

        <div class="dashboard">
            <div>
                <?php if ( empty( $trips ) ) : ?>
                    <section class="panel">
                        <div class="empty"><?php esc_html_e( 'No travel plans yet. Import a confirmation or calendar file to build your first itinerary.', 'travel-app' ); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ( $featured_trip ) : ?>
                    <section class="panel" aria-labelledby="current-trip-heading" data-ai-assistant-important>
                        <div class="section-title">
                            <h2 id="current-trip-heading"><?php echo esc_html( ! empty( $current_trips ) ? __( 'Current Trip', 'travel-app' ) : __( 'Trip Preview', 'travel-app' ) ); ?></h2>
                        </div>
                        <?php $current_trip = $featured_trip; ?>
                        <article class="current-card">
                            <h3><?php echo esc_html( $current_trip['title'] ); ?></h3>
                            <div class="trip-meta">
                                <?php foreach ( $travel_app->get_trip_summary_parts( $current_trip, $today ) as $summary_part ) : ?>
                                    <span><?php echo esc_html( $summary_part ); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="mini-timeline" data-demo-target="<?php echo esc_attr( $front_demo_control_id ); ?>" data-demo-preview>
                                <?php foreach ( $current_trip['segments'] as $step ) : ?>
                                    <?php
                                    $step_datetime = trim( (string) ( $step['date'] ?? '' ) . 'T' . ( (string) ( $step['time'] ?? '' ) ?: '00:00' ) );
                                    $step_time_label = ( ! empty( $step['end_date'] ) && $step['end_date'] === ( $step['date'] ?? '' ) && ! empty( $step['end_time'] ) )
                                        ? $travel_app->format_time_range_label( (string) ( $step['time'] ?? '' ), (string) $step['end_time'] )
                                        : (string) ( $step['time'] ?? '' );
                                    $step_start_label = trim( $travel_app->format_date_label( (string) ( $step['date'] ?? '' ) ) . ' ' . (string) ( $step['time'] ?? '' ) );
                                    $step_end_label = ! empty( $step['end_date'] ) && $step['end_date'] !== ( $step['date'] ?? '' )
                                        ? trim( $travel_app->format_date_label( (string) $step['end_date'] ) . ' ' . (string) ( $step['end_time'] ?? '' ) )
                                        : '';
                                    ?>
                                    <span hidden data-preview-item data-url="<?php echo esc_url( home_url( '/travel-app/trip/' . $current_trip['id'] . '/item/' . absint( $step['id'] ?? 0 ) . '/' ) ); ?>" data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-type="<?php echo esc_attr( (string) ( $step['type'] ?? '' ) ); ?>" data-date="<?php echo esc_attr( (string) ( $step['date'] ?? '' ) ); ?>" data-time-label="<?php echo esc_attr( $step_time_label ); ?>" data-date-time-label="<?php echo esc_attr( $step_start_label ); ?>" data-end-date="<?php echo esc_attr( (string) ( $step['end_date'] ?? '' ) ); ?>" data-end-time="<?php echo esc_attr( (string) ( $step['end_time'] ?? '' ) ); ?>" data-end-label="<?php echo esc_attr( $step_end_label ); ?>" data-location="<?php echo esc_attr( (string) ( $step['location'] ?? '' ) ); ?>" data-end-location="<?php echo esc_attr( (string) ( $step['end_location'] ?? '' ) ); ?>" data-title="<?php echo esc_attr( (string) ( $step['title'] ?? '' ) ); ?>"></span>
                                <?php endforeach; ?>
                                <?php foreach ( [ 'current' => __( 'Current', 'travel-app' ), 'next' => __( 'Next', 'travel-app' ) ] as $key => $label ) : ?>
                                    <a class="mini-step <?php echo esc_attr( $key ); ?>" href="#" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'travel-app' ); ?>">
                                        <div class="mini-label"><?php echo esc_html( $label ); ?></div>
                                        <div class="mini-title" data-preview-title><?php esc_html_e( 'No item', 'travel-app' ); ?></div>
                                        <div class="mini-countdown" data-preview-countdown></div>
                                        <div class="mini-location" data-preview-meta></div>
                                        <div class="mini-location" data-preview-location></div>
                                        <div class="mini-location" data-preview-end></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="<?php echo esc_url( $get_trip_url( $current_trip ) ); ?>#timeline-heading"><?php esc_html_e( 'Open Timeline', 'travel-app' ); ?></a>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ( ! empty( $upcoming_trips ) ) : ?>
                    <section class="panel" aria-labelledby="upcoming-heading">
                        <div class="section-title">
                            <h2 id="upcoming-heading"><?php esc_html_e( 'Upcoming Trips', 'travel-app' ); ?></h2>
                        </div>
                        <div class="trip-list">
                            <?php foreach ( $upcoming_trips as $trip_data ) : ?>
                                <a class="trip-card <?php echo (int) $trip_data['id'] === $imported ? 'highlight' : ''; ?>" href="<?php echo esc_url( $get_trip_url( $trip_data ) ); ?>">
                                    <h3><?php echo esc_html( $trip_data['title'] ); ?></h3>
                                    <div class="trip-meta">
                                        <?php foreach ( $travel_app->get_trip_summary_parts( $trip_data, $today ) as $summary_part ) : ?>
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
                                    <h3><?php echo esc_html( $trip_data['title'] ); ?></h3>
                                    <div class="trip-meta">
                                        <?php foreach ( $travel_app->get_trip_summary_parts( $trip_data, $today ) as $summary_part ) : ?>
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
                <h2 id="import-trip-heading"><?php esc_html_e( 'Import', 'travel-app' ); ?></h2>
                    <?php if ( ! empty( $quick_plan_segment ) ) : ?>
                        <?php
                        $quick_plan_trip_title = isset( $quick_plan_draft['trip_title'] )
                            ? (string) $quick_plan_draft['trip_title']
                            : ( ! empty( $quick_plan_segment['location'] ) ? (string) $quick_plan_segment['location'] : __( 'Quick Travel Plan', 'travel-app' ) );
                        ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="travel_app_import">
                            <input type="hidden" name="quick_plan_draft" value="<?php echo esc_attr( $quick_plan_draft_key ); ?>">
                            <?php wp_nonce_field( 'travel_app_import' ); ?>
                            <p class="quick-plan-confirm">
                                <?php esc_html_e( 'Review the parsed fields, update matches if needed, then choose where to save it.', 'travel-app' ); ?>
                            </p>
                            <div class="quick-plan-fields">
                                <label class="field-wide">
                                    <?php esc_html_e( 'Title', 'travel-app' ); ?>
                                    <input name="segment_title" value="<?php echo esc_attr( (string) ( $quick_plan_segment['title'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Type', 'travel-app' ); ?>
                                    <select name="segment_type">
                                        <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $quick_plan_segment['type'] ?? 'activity', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <?php esc_html_e( 'Location', 'travel-app' ); ?>
                                    <input name="segment_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['location'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'Start Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['time'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Date', 'travel-app' ); ?>
                                    <input type="date" name="segment_end_date" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_date'] ?? '' ) ); ?>">
                                </label>
                                <label>
                                    <?php esc_html_e( 'End Time', 'travel-app' ); ?>
                                    <input type="time" name="segment_end_time" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_time'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'End Location', 'travel-app' ); ?>
                                    <input name="segment_end_location" value="<?php echo esc_attr( (string) ( $quick_plan_segment['end_location'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'URL', 'travel-app' ); ?>
                                    <input type="url" name="segment_url" value="<?php echo esc_attr( (string) ( $quick_plan_segment['url'] ?? '' ) ); ?>">
                                </label>
                                <label class="field-wide">
                                    <?php esc_html_e( 'Details', 'travel-app' ); ?>
                                    <textarea name="segment_details"><?php echo esc_textarea( (string) ( $quick_plan_segment['details'] ?? '' ) ); ?></textarea>
                                </label>
                            </div>
                            <div class="quick-plan-match-list">
                                <?php if ( ! empty( $quick_plan_matches ) ) : ?>
                                    <?php foreach ( $quick_plan_matches as $index => $match ) : ?>
                                        <label class="quick-plan-choice">
                                            <input type="radio" name="quick_plan_target" value="<?php echo esc_attr( (string) ( $match['id'] ?? 0 ) ); ?>" <?php checked( 0, $index ); ?>>
                                            <span>
                                                <strong><?php echo esc_html( (string) ( $match['title'] ?? __( 'Travel plan', 'travel-app' ) ) ); ?></strong>
                                                <?php echo esc_html( $travel_app->format_date_range_label( (string) ( $match['starts_at'] ?? '' ), (string) ( $match['ends_at'] ?? '' ) ) ); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="quick-plan-confirm"><?php esc_html_e( 'No matching existing travel plan was found for these fields.', 'travel-app' ); ?></p>
                                <?php endif; ?>
                                <label class="quick-plan-choice">
                                    <input type="radio" name="quick_plan_target" value="new" <?php checked( empty( $quick_plan_matches ) ); ?>>
                                    <span>
                                        <strong><?php esc_html_e( 'Create a new travel plan', 'travel-app' ); ?></strong>
                                        <?php esc_html_e( 'Use this item as the first entry.', 'travel-app' ); ?>
                                        <input type="text" name="quick_plan_trip_title" value="<?php echo esc_attr( $quick_plan_trip_title ); ?>" aria-label="<?php esc_attr_e( 'New travel plan title', 'travel-app' ); ?>">
                                    </span>
                                </label>
                            </div>
                            <div class="quick-plan-actions">
                                <button class="ghost-button" type="submit" name="quick_plan_update_draft" value="1"><?php esc_html_e( 'Update Matches', 'travel-app' ); ?></button>
                                <button type="submit"><?php esc_html_e( 'Add Plan', 'travel-app' ); ?></button>
                            </div>
                        </form>
                    <?php else : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="travel_app_import">
                    <?php wp_nonce_field( 'travel_app_import' ); ?>
                    <label class="drop-zone" id="itinerary_drop_zone" for="itinerary_file">
                        <span class="drop-title"><?php esc_html_e( 'Drop file', 'travel-app' ); ?></span>
                        <span class="drop-file-name" id="itinerary_file_name"><?php esc_html_e( 'ICS or text file', 'travel-app' ); ?></span>
                        <input type="file" id="itinerary_file" name="itinerary_file" accept=".ics,.txt,text/calendar,text/plain">
                    </label>
                    <label for="itinerary_text"><?php esc_html_e( 'Paste confirmation or plan', 'travel-app' ); ?></label>
                    <textarea id="itinerary_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Paste itinerary text or a dated plan...', 'travel-app' ); ?>"></textarea>
                    <p class="hint"><?php echo esc_html( $has_ai ? __( 'Uses quick parsing, calendar parsing, or AI extraction.', 'travel-app' ) : __( 'Uses quick parsing, calendar parsing, or a basic parser.', 'travel-app' ) ); ?></p>
                    <button type="submit"><?php esc_html_e( 'Import', 'travel-app' ); ?></button>
                </form>
                    <?php endif; ?>
            </aside>
        </div>
    </main>

    <?php wp_app_body_close(); ?>
    <script>
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
                    : '<?php echo esc_js( __( 'ICS or text file', 'travel-app' ) ); ?>';
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
    </script>
</body>
</html>
