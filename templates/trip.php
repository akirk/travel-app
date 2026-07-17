<?php
use TravelApp\App;

global $wp_app_route;

$travel_app = App::get_instance();
$demo_mode_enabled = $travel_app->is_demo_mode_enabled();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$trip       = $travel_app->get_user_trip( $trip_id );
$updated    = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : null;
$trip_updated = isset( $_GET['trip_updated'] ) ? absint( $_GET['trip_updated'] ) : null;
$error      = isset( $_GET['travel_app_error'] ) ? sanitize_key( wp_unslash( $_GET['travel_app_error'] ) ) : '';

if ( ! $trip ) {
    status_header( 404 );
}

$trip_data = $trip ? $travel_app->format_trip_for_output( $trip ) : null;
$segments  = $trip_data['segments'] ?? [];
$timeline_segments = [];

foreach ( $segments as $segment ) {
    $segment['_index'] = (int) ( $segment['id'] ?? 0 );
    $segment['_sort']  = trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) );
    $timeline_segments[] = $segment;

    if ( 'lodging' === ( $segment['type'] ?? '' ) && ! empty( $segment['end_date'] ) ) {
        $checkout_segment = $segment;
        $checkout_segment['date'] = (string) $segment['end_date'];
        $checkout_segment['time'] = (string) ( $segment['end_time'] ?? '' );
        $checkout_segment['title'] = (string) ( $segment['title'] ?: __( 'Lodging', 'travel-app' ) );
        $checkout_segment['end_date'] = '';
        $checkout_segment['_timeline_kind'] = 'checkout';
        $checkout_segment['_sort'] = trim( $checkout_segment['date'] . ' ' . $checkout_segment['time'] );
        $timeline_segments[] = $checkout_segment;
    }
}

usort( $timeline_segments, static function( array $a, array $b ): int {
    return strcmp( (string) ( $a['_sort'] ?? '' ), (string) ( $b['_sort'] ?? '' ) );
} );

$segments_by_day = [];
foreach ( $timeline_segments as $segment ) {
    $day = ! empty( $segment['date'] ) ? (string) $segment['date'] : 'unscheduled';
    $segments_by_day[ $day ][] = $segment;
}

$unscheduled_segments = $segments_by_day['unscheduled'] ?? [];
unset( $segments_by_day['unscheduled'] );

$demo_start = $trip_data['starts_at'] ?? '';
if ( '' === $demo_start ) {
    $demo_start = gmdate( 'Y-m-d' );
}
$demo_start_time = $demo_start . 'T12:00';
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( $trip_data ? $trip_data['title'] : __( 'Travel Plan', 'travel-app' ) ); ?></title>
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
        main { max-width: 980px; margin: 0 auto; padding: 32px 18px 56px; }
        a { color: var(--wp-app-color-link); }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: clamp(2rem, 5vw, 3.5rem); line-height: 1.04; margin-bottom: 12px; letter-spacing: 0; }
        h2 { font-size: 1.15rem; margin-bottom: 14px; }
        h3 { font-size: 1rem; margin-bottom: 5px; }
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
        .trip-title-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }
        .trip-title-form label { margin: 0; }
        .trip-title-form input { font-size: 1.35rem; font-weight: 750; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: var(--wp-app-color-muted); margin-bottom: 24px; }
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
        .time-marker::before {
            content: "";
            position: absolute;
            left: -33px;
            top: -6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #c62828;
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
        .timeline-item {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            background: var(--wp-app-color-background);
            color: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        .timeline-item:hover,
        .timeline-item:focus,
        .timeline-item:focus-visible,
        .timeline-item:hover *,
        .timeline-item:focus *,
        .timeline-item:focus-visible * {
            text-decoration: none;
        }
        .timeline-item:hover {
            border-color: var(--wp-app-color-link);
            background: var(--wp-app-color-surface);
        }
        .timeline-item:focus-visible {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 2px;
        }
        .timeline-item.current {
            outline: 2px solid var(--wp-app-color-link);
            outline-offset: 1px;
        }
        .time { color: var(--wp-app-color-muted); font-weight: 750; }
        .type { color: var(--wp-app-color-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0; }
        .title { font-weight: 750; overflow-wrap: anywhere; }
        .detail { color: var(--wp-app-color-muted); overflow-wrap: anywhere; }
        .timeline-item .detail,
        .summary-grid .detail {
            font-size: 0.88rem;
            line-height: 1.42;
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
        .add-item {
            display: inline-block;
            margin-bottom: 14px;
            max-width: 100%;
        }
        .add-item summary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: auto;
            padding: 8px 12px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            background: var(--wp-app-color-background);
            font-weight: 750;
            white-space: nowrap;
        }
        .add-item summary::before {
            content: "+";
            font-size: 1.15rem;
            line-height: 1;
        }
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
        .danger-zone {
            margin-top: 28px;
            border-top: 1px solid var(--wp-app-color-border);
            padding-top: 18px;
            color: var(--wp-app-color-muted);
        }
        .delete-button {
            background: transparent;
            color: #9f1f1f;
            border-color: rgba(159, 31, 31, 0.36);
        }
        .empty { color: var(--wp-app-color-muted); }
        @media (max-width: 680px) {
            .timeline-item, .summary-grid, .edit-form { grid-template-columns: 1fr; }
            .trip-title-form { grid-template-columns: 1fr; }
            .date-time-group { grid-template-columns: 1fr; }
            .demo-controls label { min-width: 100%; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main>
        <div class="topbar">
            <a href="<?php echo esc_url( home_url( '/travel-app/' ) ); ?>"><?php esc_html_e( 'Back to Travel App', 'travel-app' ); ?></a>
        </div>

        <?php if ( null !== $trip_updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Travel plan updated.', 'travel-app' ); ?></div>
        <?php elseif ( null !== $updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Itinerary item updated.', 'travel-app' ); ?></div>
        <?php elseif ( $error ) : ?>
            <div class="notice error" role="alert"><?php esc_html_e( 'The requested change could not be saved.', 'travel-app' ); ?></div>
        <?php endif; ?>

        <?php if ( ! $trip_data ) : ?>
            <section class="panel">
                <h1><?php esc_html_e( 'Travel plan not found', 'travel-app' ); ?></h1>
                <p class="empty"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'travel-app' ); ?></p>
            </section>
        <?php else : ?>
            <header>
                <h1 class="screen-reader-text"><?php echo esc_html( $trip_data['title'] ); ?></h1>
                <form class="trip-title-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="travel_app_update_trip">
                    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                    <?php wp_nonce_field( 'travel_app_update_trip_' . $trip_data['id'] ); ?>
                    <label for="trip_title">
                        <span class="screen-reader-text"><?php esc_html_e( 'Travel plan title', 'travel-app' ); ?></span>
                        <input type="text" id="trip_title" name="trip_title" value="<?php echo esc_attr( $trip_data['title'] ); ?>" required>
                    </label>
                    <button type="submit"><?php esc_html_e( 'Save', 'travel-app' ); ?></button>
                </form>
                <div class="meta">
                    <?php foreach ( $travel_app->get_trip_summary_parts( $trip_data ) as $summary_part ) : ?>
                        <span><?php echo esc_html( $summary_part ); ?></span>
                    <?php endforeach; ?>
                    <span><?php echo esc_html( sprintf( _n( '%d item', '%d items', count( $segments ), 'travel-app' ), count( $segments ) ) ); ?></span>
                </div>
            </header>

            <section class="panel" aria-labelledby="timeline-heading" data-ai-assistant-important>
                <h2 id="timeline-heading"><?php esc_html_e( 'Timeline', 'travel-app' ); ?></h2>
                <?php
                $demo_control_id = 'trip-' . (string) $trip_data['id'];
                $demo_control_value = $demo_start_time;
                if ( $demo_mode_enabled ) {
                    require __DIR__ . '/partials/demo-controls.php';
                }
                ?>

                <details class="item add-item">
                    <summary>
                        <?php esc_html_e( 'Add Item', 'travel-app' ); ?>
                    </summary>
                    <form class="edit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="travel_app_add_segment">
                        <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                        <?php wp_nonce_field( 'travel_app_add_segment_' . $trip_data['id'] ); ?>
                        <label class="field-wide">
                            <?php esc_html_e( 'Title', 'travel-app' ); ?>
                            <input name="segment_title">
                        </label>
                        <label class="field-wide">
                            <?php esc_html_e( 'Type', 'travel-app' ); ?>
                            <select name="segment_type">
                                <?php foreach ( [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ] as $type ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <?php esc_html_e( 'Location', 'travel-app' ); ?>
                            <input name="segment_location">
                        </label>
                        <label>
                            <?php esc_html_e( 'End Location', 'travel-app' ); ?>
                            <input name="segment_end_location">
                        </label>
                        <div class="date-time-group">
                            <label>
                                <?php esc_html_e( 'Start Date', 'travel-app' ); ?>
                                <input type="date" name="segment_date">
                            </label>
                            <label>
                                <?php esc_html_e( 'Start Time', 'travel-app' ); ?>
                                <input type="time" name="segment_time">
                            </label>
                        </div>
                        <div class="date-time-group">
                            <label>
                                <?php esc_html_e( 'End Date', 'travel-app' ); ?>
                                <input type="date" name="segment_end_date">
                            </label>
                            <label>
                                <?php esc_html_e( 'End Time', 'travel-app' ); ?>
                                <input type="time" name="segment_end_time">
                            </label>
                        </div>
                        <label class="field-wide">
                            <?php esc_html_e( 'Details', 'travel-app' ); ?>
                            <textarea name="segment_details"></textarea>
                        </label>
                        <div class="form-actions">
                            <button type="submit"><?php esc_html_e( 'Add Item', 'travel-app' ); ?></button>
                        </div>
                    </form>
                </details>

                <?php if ( empty( $segments_by_day ) ) : ?>
                    <p class="empty"><?php esc_html_e( 'No timeline items were found.', 'travel-app' ); ?></p>
                <?php else : ?>
                    <div class="timeline" id="timeline" data-demo-target="<?php echo esc_attr( $demo_control_id ); ?>">
                        <div class="time-marker"><span class="time-marker-label"></span></div>
                        <?php foreach ( $segments_by_day as $day => $day_segments ) : ?>
                            <section class="timeline-day" data-date="<?php echo esc_attr( $day ); ?>">
                                <h3 class="day-heading"><?php echo esc_html( $day ); ?></h3>
                                <?php foreach ( $day_segments as $segment ) : ?>
                                    <?php $index = (int) $segment['_index']; ?>
                                    <?php $timeline_kind = (string) ( $segment['_timeline_kind'] ?? 'start' ); ?>
                                    <?php $segment_anchor = 'segment-' . $index . ( 'checkout' === $timeline_kind ? '-checkout' : '' ); ?>
                                    <?php $segment_datetime = trim( (string) ( $segment['date'] ?? '' ) . 'T' . ( (string) ( $segment['time'] ?? '' ) ?: '00:00' ) ); ?>
                                    <a class="timeline-item" id="<?php echo esc_attr( $segment_anchor ); ?>" href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_data['id'] . '/item/' . $index . '/' ) ); ?>" data-date="<?php echo esc_attr( (string) ( $segment['date'] ?? '' ) ); ?>" data-datetime="<?php echo esc_attr( $segment_datetime ); ?>">
                                            <div class="time"><?php echo esc_html( $segment['time'] ?: ' ' ); ?></div>
                                            <div>
                                                <div class="type"><?php echo esc_html( ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) ) ); ?></div>
                                                <div class="title"><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></div>
                                                <?php if ( ! empty( $segment['end_date'] ) ) : ?>
                                                    <div class="detail"><?php echo esc_html( $travel_app->get_segment_date_range_label( $segment ) ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $segment['location'] ) ) : ?>
                                                    <div class="detail"><?php echo esc_html( $segment['location'] ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                    <div class="detail"><?php echo esc_html( sprintf( __( 'To: %s', 'travel-app' ), $segment['end_location'] ) ); ?></div>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $segment['details'] ) ) : ?>
                                                    <div class="detail"><?php echo esc_html( $segment['details'] ); ?></div>
                                                <?php endif; ?>
                                            </div>
                                    </a>
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
                            <a class="item unscheduled-link" id="segment-<?php echo esc_attr( (string) $index ); ?>" href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_data['id'] . '/item/' . $index . '/' ) ); ?>">
                                    <div class="summary-grid">
                                        <span class="time"><?php echo esc_html( trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) ) ); ?></span>
                                        <span>
                                            <span class="type"><?php echo esc_html( ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) ) ); ?></span><br>
                                            <span class="title"><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></span>
                                            <?php if ( ! empty( $segment['end_date'] ) ) : ?>
                                                <br><span class="detail"><?php echo esc_html( $travel_app->get_segment_date_range_label( $segment ) ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $segment['location'] ) ) : ?>
                                                <br><span class="detail"><?php echo esc_html( $segment['location'] ); ?></span>
                                            <?php endif; ?>
                                            <?php if ( ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                                                <br><span class="detail"><?php echo esc_html( sprintf( __( 'To: %s', 'travel-app' ), $segment['end_location'] ) ); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="detail"><?php esc_html_e( 'Open', 'travel-app' ); ?></span>
                                    </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="danger-zone" aria-labelledby="delete-heading">
                <h2 id="delete-heading"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></h2>
                <p><?php esc_html_e( 'This deletes the travel plan and moves its itinerary items to the trash.', 'travel-app' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this travel plan?', 'travel-app' ) ); ?>');">
                    <input type="hidden" name="action" value="travel_app_delete">
                    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                    <?php wp_nonce_field( 'travel_app_delete_' . $trip_data['id'] ); ?>
                    <button class="delete-button" type="submit"><?php esc_html_e( 'Delete Travel Plan', 'travel-app' ); ?></button>
                </form>
            </section>
        <?php endif; ?>
    </main>

    <?php wp_app_body_close(); ?>
</body>
</html>
