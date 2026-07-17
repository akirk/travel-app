<?php
use TravelApp\App;

global $wp_app_route;

$travel_app = App::get_instance();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$index      = isset( $wp_app_route['params']['item_id'] ) ? absint( $wp_app_route['params']['item_id'] ) : absint( get_query_var( 'item_id' ) );
$trip       = $travel_app->get_user_trip( $trip_id );
$trip_data  = $trip ? $travel_app->format_trip_for_output( $trip ) : null;
$segment    = $trip ? $travel_app->get_user_trip_segment( $trip_id, $index ) : null;
$updated    = isset( $_GET['updated'] );
$error      = isset( $_GET['travel_app_error'] ) ? sanitize_key( wp_unslash( $_GET['travel_app_error'] ) ) : '';

if ( ! $trip || ! $segment ) {
    status_header( 404 );
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( $segment ? ( $segment['title'] ?: __( 'Itinerary Item', 'travel-app' ) ) : __( 'Itinerary Item', 'travel-app' ) ); ?></title>
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
        main { max-width: 760px; margin: 0 auto; padding: 32px 18px 56px; }
        a { color: var(--wp-app-color-link); }
        h1, h2, p { margin-top: 0; }
        h1 { font-size: clamp(1.8rem, 4vw, 3rem); line-height: 1.08; margin-bottom: 12px; letter-spacing: 0; }
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
        textarea { min-height: 120px; resize: vertical; }
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
        .edit-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .field-wide { grid-column: 1 / -1; }
        .date-time-group {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .form-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; }
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
            .edit-form { grid-template-columns: 1fr; }
            .date-time-group { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main>
        <div class="topbar">
            <a href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_id . '/#segment-' . $index ) ); ?>"><?php esc_html_e( 'Back to Timeline', 'travel-app' ); ?></a>
        </div>

        <?php if ( $updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Itinerary item saved.', 'travel-app' ); ?></div>
        <?php elseif ( $error ) : ?>
            <div class="notice error" role="alert"><?php esc_html_e( 'The requested change could not be saved.', 'travel-app' ); ?></div>
        <?php endif; ?>

        <?php if ( ! $trip_data || ! $segment ) : ?>
            <section class="panel">
                <h1><?php esc_html_e( 'Itinerary item not found', 'travel-app' ); ?></h1>
                <p class="empty"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'travel-app' ); ?></p>
            </section>
        <?php else : ?>
            <header>
                <h1><?php echo esc_html( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ); ?></h1>
                <div class="meta">
                    <span><?php echo esc_html( $trip_data['title'] ); ?></span>
                    <span><?php echo esc_html( ucfirst( $segment['type'] ?: __( 'other', 'travel-app' ) ) ); ?></span>
                    <?php if ( $segment['date'] || $segment['end_date'] || $segment['time'] || $segment['end_time'] ) : ?>
                        <span><?php echo esc_html( $travel_app->get_segment_date_time_range_label( $segment ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( $segment['location'] ) : ?>
                        <span><?php echo esc_html( $segment['location'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $segment['end_location'] ) && $segment['end_location'] !== ( $segment['location'] ?? '' ) ) : ?>
                        <span><?php echo esc_html( sprintf( __( 'To: %s', 'travel-app' ), $segment['end_location'] ) ); ?></span>
                    <?php endif; ?>
                </div>
            </header>

            <section class="panel" aria-labelledby="edit-item-heading">
                <h2 id="edit-item-heading"><?php esc_html_e( 'Edit Item', 'travel-app' ); ?></h2>
                <?php require __DIR__ . '/partials/segment-form.php'; ?>
            </section>

            <section class="danger-zone" aria-labelledby="delete-item-heading">
                <h2 id="delete-item-heading"><?php esc_html_e( 'Delete Item', 'travel-app' ); ?></h2>
                <p><?php esc_html_e( 'This removes the item from the travel plan.', 'travel-app' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this itinerary item?', 'travel-app' ) ); ?>');">
                    <input type="hidden" name="action" value="travel_app_delete_segment">
                    <input type="hidden" name="trip_id" value="<?php echo esc_attr( (string) $trip_data['id'] ); ?>">
                    <input type="hidden" name="segment_index" value="<?php echo esc_attr( (string) $index ); ?>">
                    <?php wp_nonce_field( 'travel_app_delete_segment_' . $trip_data['id'] . '_' . $index ); ?>
                    <button class="delete-button" type="submit"><?php esc_html_e( 'Delete Item', 'travel-app' ); ?></button>
                </form>
            </section>
        <?php endif; ?>
    </main>

    <?php wp_app_body_close(); ?>
</body>
</html>
