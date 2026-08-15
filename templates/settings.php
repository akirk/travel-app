<?php
use TravelApp\App;

$travel_app = App::get_instance();
$allow_delegated_trip_creation = $travel_app->user_allows_delegated_trip_creation( get_current_user_id() );
$delegation_capability_options = $travel_app->get_delegation_capability_options();
$delegated_trip_creation_capability = $travel_app->get_delegated_trip_creation_capability( get_current_user_id() );
$global_trip_editor_capability = $travel_app->get_global_trip_editor_capability( get_current_user_id() );
$settings_updated = isset( $_GET['settings_updated'] );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( __( 'Travel App Settings', 'travel-app' ) ); ?></title>
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
        h1, h2, p { margin-top: 0; }
        h1 { font-size: 2rem; line-height: 1.1; margin-bottom: 10px; letter-spacing: 0; }
        .subheader {
            color: var(--wp-app-color-muted);
            margin-bottom: 24px;
            max-width: 620px;
        }
        a { color: var(--wp-app-color-link); }
        .notice {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid rgba(15, 107, 66, 0.28);
            border-radius: 8px;
            background: rgba(15, 107, 66, 0.08);
        }
        .settings-form {
            display: grid;
            gap: 14px;
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
            font-size: 0.9rem;
        }
        .setting-field {
            display: grid;
            gap: 6px;
        }
        .setting-field label {
            font-weight: 700;
        }
        .setting-field select {
            width: 100%;
            max-width: 360px;
            box-sizing: border-box;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 6px;
            padding: 9px 10px;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
            font: inherit;
        }
        .setting-help {
            color: var(--wp-app-color-muted);
            font-size: 0.9rem;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
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
    </style>
</head>
<body <?php body_class( 'wp-app-body travel-app-settings' ); ?>>
    <?php wp_app_body_open(); ?>
    <main>
        <h1><?php esc_html_e( 'Settings', 'travel-app' ); ?></h1>
        <p class="subheader"><?php esc_html_e( 'Manage preferences for your travel plans and collaboration.', 'travel-app' ); ?></p>
        <?php if ( $settings_updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Settings saved.', 'travel-app' ); ?></div>
        <?php endif; ?>
        <section aria-labelledby="delegation-settings-heading">
            <h2 id="delegation-settings-heading"><?php esc_html_e( 'Delegation', 'travel-app' ); ?></h2>
            <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="travel_app_update_user_settings">
                <?php wp_nonce_field( 'travel_app_update_user_settings' ); ?>
                <label class="setting-option">
                    <input type="checkbox" name="allow_delegated_trip_creation" value="1" <?php checked( $allow_delegated_trip_creation ); ?>>
                    <span>
                        <strong><?php esc_html_e( 'Let other users create travel plans for me', 'travel-app' ); ?></strong>
                        <span><?php esc_html_e( 'Other users on this WordPress site can create trips for you. They can edit those trips on your behalf, and you can change access later in each trip\'s settings.', 'travel-app' ); ?></span>
                    </span>
                </label>
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
