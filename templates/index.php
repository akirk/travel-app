<?php
use TravelApp\App;

$travel_app = App::get_instance();
$trips      = array_map( [ $travel_app, 'format_trip_for_output' ], $travel_app->get_user_trips() );
$imported   = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
$deleted    = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0;
$error      = isset( $_GET['travel_app_error'] ) ? sanitize_key( wp_unslash( $_GET['travel_app_error'] ) ) : '';
$has_ai     = function_exists( 'wp_ai_client_prompt' );
$has_ai_assistant = defined( 'AI_ASSISTANT_VERSION' ) || class_exists( '\AI_Assistant' );
$today      = current_time( 'Y-m-d' );

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

$get_trip_url = static function( array $trip_data ): string {
    return home_url( '/travel-app/trip/' . absint( $trip_data['id'] ?? 0 ) . '/' );
};

$get_days_label = static function( array $trip_data ) use ( $today ): string {
    $starts = (string) ( $trip_data['starts_at'] ?? '' );
    $ends = (string) ( $trip_data['ends_at'] ?? '' );

    if ( '' === $starts ) {
        return '';
    }

    $today_date = date_create_immutable( $today );
    $start_date = date_create_immutable( $starts );
    $end_date = '' !== $ends ? date_create_immutable( $ends ) : null;

    if ( ! $today_date || ! $start_date ) {
        return '';
    }

    if ( $start_date > $today_date ) {
        $days = (int) $today_date->diff( $start_date )->format( '%a' );
        return sprintf( _n( 'Starts tomorrow', 'Starts in %d days', $days, 'travel-app' ), $days );
    }

    if ( $end_date && $end_date < $today_date ) {
        $days = (int) $end_date->diff( $today_date )->format( '%a' );
        return sprintf( _n( 'Ended yesterday', 'Ended %d days ago', $days, 'travel-app' ), $days );
    }

    return __( 'Active now', 'travel-app' );
};

$get_duration_label = static function( array $trip_data ): string {
    $starts = (string) ( $trip_data['starts_at'] ?? '' );
    $ends = (string) ( $trip_data['ends_at'] ?? '' );
    if ( '' === $starts || '' === $ends ) {
        return '';
    }

    $start_date = date_create_immutable( $starts );
    $end_date = date_create_immutable( $ends );
    if ( ! $start_date || ! $end_date || $end_date < $start_date ) {
        return '';
    }

    $days = (int) $start_date->diff( $end_date )->format( '%a' ) + 1;
    return sprintf( _n( '1 day', '%d days', $days, 'travel-app' ), $days );
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
        .import-panel textarea {
            width: 100%;
            min-height: 118px;
            box-sizing: border-box;
            resize: vertical;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            padding: 10px;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
            font: inherit;
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
        .trip-card:hover { border-color: var(--wp-app-color-link); }
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
            border-left: 3px solid var(--wp-app-color-border);
            padding-left: 12px;
        }
        .mini-step.current { border-left-color: var(--wp-app-color-link); }
        .mini-label { color: var(--wp-app-color-muted); font-size: 0.78rem; text-transform: uppercase; }
        .mini-title { font-weight: 750; overflow-wrap: anywhere; }
        .mini-location { color: var(--wp-app-color-muted); overflow-wrap: anywhere; }
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
            .app-header, .dashboard, .mini-timeline { grid-template-columns: 1fr; }
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
                <p class="lede"><?php esc_html_e( 'Keep pasted confirmations and calendar files as private travel plans.', 'travel-app' ); ?></p>
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

        <div class="dashboard">
            <div>
                <?php if ( empty( $trips ) ) : ?>
                    <section class="panel">
                        <div class="empty"><?php esc_html_e( 'No travel plans yet. Import a confirmation or calendar file to build your first itinerary.', 'travel-app' ); ?></div>
                    </section>
                <?php endif; ?>

                <?php if ( ! empty( $current_trips ) ) : ?>
                    <section class="panel" aria-labelledby="current-trip-heading" data-ai-assistant-important>
                        <div class="section-title">
                            <h2 id="current-trip-heading"><?php esc_html_e( 'Current Trip', 'travel-app' ); ?></h2>
                            <span><?php echo esc_html( $today ); ?></span>
                        </div>
                        <?php $current_trip = $current_trips[0]; ?>
                        <article class="current-card">
                            <h3><?php echo esc_html( $current_trip['title'] ); ?></h3>
                            <div class="trip-meta">
                                <?php if ( $current_trip['destination'] ) : ?>
                                    <span><?php echo esc_html( $current_trip['destination'] ); ?></span>
                                <?php endif; ?>
                                <span><?php echo esc_html( trim( $current_trip['starts_at'] . ' - ' . $current_trip['ends_at'], ' -' ) ); ?></span>
                                <?php if ( $get_days_label( $current_trip ) ) : ?><span><?php echo esc_html( $get_days_label( $current_trip ) ); ?></span><?php endif; ?>
                                <?php if ( $get_duration_label( $current_trip ) ) : ?><span><?php echo esc_html( $get_duration_label( $current_trip ) ); ?></span><?php endif; ?>
                            </div>
                            <?php
                            $demo_control_id = 'front-current-' . (string) $current_trip['id'];
                            $demo_control_value = ( $current_trip['starts_at'] ?: $today ) . 'T12:00';
                            require __DIR__ . '/partials/demo-controls.php';
                            ?>
                            <div class="mini-timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>" data-demo-preview>
                                <?php foreach ( $current_trip['segments'] as $step ) : ?>
                                    <?php
                                    $step_datetime = trim( (string) ( $step['date'] ?? '' ) . 'T' . ( (string) ( $step['time'] ?? '' ) ?: '00:00' ) );
                                    ?>
                                    <span hidden data-preview-item data-datetime="<?php echo esc_attr( $step_datetime ); ?>" data-date="<?php echo esc_attr( (string) ( $step['date'] ?? '' ) ); ?>" data-time="<?php echo esc_attr( (string) ( $step['time'] ?? '' ) ); ?>" data-location="<?php echo esc_attr( (string) ( $step['location'] ?? '' ) ); ?>" data-title="<?php echo esc_attr( (string) ( $step['title'] ?? '' ) ); ?>"></span>
                                <?php endforeach; ?>
                                <?php foreach ( [ 'current' => __( 'Current', 'travel-app' ), 'next' => __( 'Next', 'travel-app' ) ] as $key => $label ) : ?>
                                    <div class="mini-step <?php echo esc_attr( $key ); ?>" data-preview-slot="<?php echo esc_attr( $key ); ?>" data-empty-title="<?php esc_attr_e( 'No item', 'travel-app' ); ?>">
                                        <div class="mini-label"><?php echo esc_html( $label ); ?></div>
                                        <div class="mini-title" data-preview-title><?php esc_html_e( 'No item', 'travel-app' ); ?></div>
                                        <div class="mini-location" data-preview-meta></div>
                                    </div>
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
                                        <?php if ( $trip_data['destination'] ) : ?><span><?php echo esc_html( $trip_data['destination'] ); ?></span><?php endif; ?>
                                        <span><?php echo esc_html( trim( $trip_data['starts_at'] . ' - ' . $trip_data['ends_at'], ' -' ) ); ?></span>
                                        <?php if ( $get_days_label( $trip_data ) ) : ?><span><?php echo esc_html( $get_days_label( $trip_data ) ); ?></span><?php endif; ?>
                                        <?php if ( $get_duration_label( $trip_data ) ) : ?><span><?php echo esc_html( $get_duration_label( $trip_data ) ); ?></span><?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( ! empty( $past_trips ) ) : ?>
                    <section class="panel" aria-labelledby="past-heading">
                        <div class="section-title">
                            <h2 id="past-heading"><?php esc_html_e( 'Past Trips', 'travel-app' ); ?></h2>
                        </div>
                        <div class="trip-list">
                            <?php foreach ( $past_trips as $trip_data ) : ?>
                                <a class="trip-card" href="<?php echo esc_url( $get_trip_url( $trip_data ) ); ?>">
                                    <h3><?php echo esc_html( $trip_data['title'] ); ?></h3>
                                    <div class="trip-meta">
                                        <?php if ( $trip_data['destination'] ) : ?><span><?php echo esc_html( $trip_data['destination'] ); ?></span><?php endif; ?>
                                        <span><?php echo esc_html( trim( $trip_data['starts_at'] . ' - ' . $trip_data['ends_at'], ' -' ) ); ?></span>
                                        <?php if ( $get_days_label( $trip_data ) ) : ?><span><?php echo esc_html( $get_days_label( $trip_data ) ); ?></span><?php endif; ?>
                                        <?php if ( $get_duration_label( $trip_data ) ) : ?><span><?php echo esc_html( $get_duration_label( $trip_data ) ); ?></span><?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="panel import-panel" aria-labelledby="import-trip-heading">
                <h2 id="import-trip-heading"><?php esc_html_e( 'Import', 'travel-app' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="travel_app_import">
                    <?php wp_nonce_field( 'travel_app_import' ); ?>
                    <label class="drop-zone" id="itinerary_drop_zone" for="itinerary_file">
                        <span class="drop-title"><?php esc_html_e( 'Drop file', 'travel-app' ); ?></span>
                        <span class="drop-file-name" id="itinerary_file_name"><?php esc_html_e( 'ICS or text file', 'travel-app' ); ?></span>
                        <input type="file" id="itinerary_file" name="itinerary_file" accept=".ics,.txt,text/calendar,text/plain">
                    </label>
                    <label for="itinerary_text"><?php esc_html_e( 'Paste confirmation', 'travel-app' ); ?></label>
                    <textarea id="itinerary_text" name="itinerary_text" placeholder="<?php esc_attr_e( 'Paste itinerary text...', 'travel-app' ); ?>"></textarea>
                    <p class="hint"><?php echo esc_html( $has_ai ? __( 'Uses calendar parsing or AI extraction.', 'travel-app' ) : __( 'Uses calendar parsing or a basic parser.', 'travel-app' ) ); ?></p>
                    <button type="submit"><?php esc_html_e( 'Import', 'travel-app' ); ?></button>
                </form>
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
