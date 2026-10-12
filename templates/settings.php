<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are render-local state.
use TravelApp\App;

$travel_app = App::get_instance();
$allow_delegated_trip_creation = $travel_app->user_allows_delegated_trip_creation( get_current_user_id() );
$delegation_capability_options = $travel_app->get_delegation_capability_options();
$delegated_trip_creation_capability = $travel_app->get_delegated_trip_creation_capability( get_current_user_id() );
$global_trip_editor_capability = $travel_app->get_global_trip_editor_capability( get_current_user_id() );
$menu_preferences = $travel_app->get_menu_preferences();
$example_trips = \TravelApp\Trip::for_current_user();
$example_title = __( 'Example trip', 'travel-app' );
$example_dates = __( 'Oct 11-16', 'travel-app' );
if ( ! empty( $example_trips ) ) {
    $example_trip = $example_trips[0];
    $example_title = $example_trip->title;
    $example_dates = '';
    if ( '' !== $example_trip->starts_at ) {
        $example_start = new \DateTimeImmutable( $example_trip->starts_at );
        $example_dates = wp_date( 'M j', $example_start->getTimestamp() );
        if ( '' !== $example_trip->ends_at && $example_trip->ends_at !== $example_trip->starts_at ) {
            $example_end = new \DateTimeImmutable( $example_trip->ends_at );
            $example_end_format = $example_start->format( 'Y-m' ) === $example_end->format( 'Y-m' ) ? 'j' : 'M j';
            $example_dates .= '-' . wp_date( $example_end_format, $example_end->getTimestamp() );
        }
    }
}

$settings_updated = $travel_app->has_query_arg( 'settings_updated' );

$travel_app->enqueue_template_assets( 'settings' );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_app_the_title( __( 'Travel App Settings', 'travel-app' ) ); ?></title>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <?php wp_app_head(); ?>
</head>
<body <?php body_class( 'wp-app-body travel-app-settings' ); ?>>
    <?php wp_app_body_open(); ?>
    <main>
        <h1><?php esc_html_e( 'Settings', 'travel-app' ); ?></h1>
        <p class="subheader"><?php esc_html_e( 'Manage preferences for your travel plans and collaboration.', 'travel-app' ); ?></p>
        <?php if ( $settings_updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Settings saved.', 'travel-app' ); ?></div>
        <?php endif; ?>
        <section aria-labelledby="menu-settings-heading">
            <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="travel_app_update_user_settings">
                <?php wp_nonce_field( 'travel_app_update_user_settings' ); ?>
                <h2 id="menu-settings-heading"><?php esc_html_e( 'Menu', 'travel-app' ); ?></h2>
                <label class="setting-option">
                    <input type="checkbox" name="menu_active_trips" value="1" <?php checked( $menu_preferences['active'] ); ?>>
                    <span><strong><?php esc_html_e( 'Active trips', 'travel-app' ); ?></strong><span><?php esc_html_e( 'Show trips happening today.', 'travel-app' ); ?></span></span>
                </label>
                <label class="setting-option">
                    <input type="checkbox" name="menu_last_trip" value="1" <?php checked( $menu_preferences['last'] ); ?>>
                    <span><strong><?php esc_html_e( 'Last trip', 'travel-app' ); ?></strong><span><?php esc_html_e( 'Show the most recently ended trip.', 'travel-app' ); ?></span></span>
                </label>
                <label class="setting-option">
                    <input type="checkbox" name="menu_upcoming_trips" value="1" <?php checked( $menu_preferences['upcoming'] ); ?>>
                    <span><strong><?php esc_html_e( 'Upcoming trips', 'travel-app' ); ?></strong><span><?php esc_html_e( 'Show all trips starting after today.', 'travel-app' ); ?></span></span>
                </label>
                <div class="setting-field">
                    <label for="menu_title_format"><?php esc_html_e( 'Trip title format', 'travel-app' ); ?></label>
                    <select id="menu_title_format" name="menu_title_format">
                        <option value="title_dates" <?php selected( $menu_preferences['format'], 'title_dates' ); ?>><?php echo esc_html( sprintf( __( 'Title followed by dates (%s)', 'travel-app' ), trim( $example_title . ' ' . $example_dates ) ) ); ?></option>
                        <option value="title" <?php selected( $menu_preferences['format'], 'title' ); ?>><?php echo esc_html( sprintf( __( 'Title only (%s)', 'travel-app' ), $example_title ) ); ?></option>
                        <option value="dates_title" <?php selected( $menu_preferences['format'], 'dates_title' ); ?>><?php echo esc_html( sprintf( __( 'Dates followed by title (%s)', 'travel-app' ), trim( $example_dates . ' ' . $example_title ) ) ); ?></option>
                    </select>
                </div>
                <h2 id="delegation-settings-heading"><?php esc_html_e( 'Delegation', 'travel-app' ); ?></h2>
                <label class="setting-option">
                    <input type="checkbox" name="allow_delegated_trip_creation" value="1" <?php checked( $allow_delegated_trip_creation ); ?>>
                    <span>
                        <strong><?php esc_html_e( 'Let other users create travel plans for me', 'travel-app' ); ?></strong>
                        <span><?php esc_html_e( 'Other users on this WordPress site can create trips for you. They can edit those trips on your behalf, and you can change access later in each trip\'s settings.', 'travel-app' ); ?></span>
                    </span>
                </label>
                <?php if ( $allow_delegated_trip_creation ) : ?>
                    <div class="setting-field">
                        <label for="delegated_trip_creation_capability"><?php esc_html_e( 'Minimum permission to create trips for me', 'travel-app' ); ?></label>
                        <select id="delegated_trip_creation_capability" name="delegated_trip_creation_capability">
                            <?php foreach ( $delegation_capability_options as $capability => $label ) : ?>
                                <option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $delegated_trip_creation_capability, $capability ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="setting-help"><?php esc_html_e( 'Only users at this level or higher will see you as a Create for option.', 'travel-app' ); ?></span>
                    </div>
                <?php endif; ?>
                <div class="setting-field">
                    <label for="global_trip_editor_capability"><?php esc_html_e( 'Who can modify my travel plans', 'travel-app' ); ?></label>
                    <select id="global_trip_editor_capability" name="global_trip_editor_capability">
                        <option value="none" <?php selected( $global_trip_editor_capability, 'none' ); ?>><?php esc_html_e( 'Only me and trip editors', 'travel-app' ); ?></option>
                        <?php foreach ( $delegation_capability_options as $capability => $label ) : ?>
                            <option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $global_trip_editor_capability, $capability ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="setting-help"><?php esc_html_e( 'Users at this level or higher can edit all of your trips. Per-trip editors can still be changed in each trip\'s settings.', 'travel-app' ); ?></span>
                </div>
                <div class="actions">
                    <a href="<?php echo esc_url( home_url( '/travel-app/' ) ); ?>"><?php esc_html_e( 'Back to Travel App', 'travel-app' ); ?></a>
                    <button type="submit"><?php esc_html_e( 'Save Settings', 'travel-app' ); ?></button>
                </div>
            </form>
        </section>
    </main>
    <?php wp_app_body_close(); ?>
</body>
</html>
