<?php
use Traveler\App;
use Traveler\Parser\AiParser;

$traveler = App::get_instance();
$allow_delegated_trip_creation = $traveler->user_allows_delegated_trip_creation( get_current_user_id() );
$delegation_capability_options = $traveler->get_delegation_capability_options();
$delegated_trip_creation_capability = $traveler->get_delegated_trip_creation_capability( get_current_user_id() );
$global_trip_editor_capability = $traveler->get_global_trip_editor_capability( get_current_user_id() );
$settings_updated = isset( $_GET['settings_updated'] );
$has_ai = AiParser::is_available();
$has_ai_assistant = defined( 'AI_ASSISTANT_VERSION' ) || class_exists( '\\AI_Assistant' );
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( __( 'Traveler Settings', 'traveler' ) ); ?></title>
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
        .settings-section + .settings-section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--wp-app-color-border);
        }
        .integration-list {
            display: grid;
            gap: 14px;
            margin: 0;
        }
        .integration-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 4px 16px;
            align-items: baseline;
        }
        .integration-item dt { font-weight: 700; }
        .integration-item dd { margin: 0; font-weight: 700; }
        .integration-item p {
            grid-column: 1 / -1;
            margin: 0;
            color: var(--wp-app-color-muted);
            font-size: 0.9rem;
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
<body <?php body_class( 'wp-app-body traveler-settings' ); ?>>
    <?php wp_app_body_open(); ?>
    <main>
        <h1><?php esc_html_e( 'Settings', 'traveler' ); ?></h1>
        <p class="subheader"><?php esc_html_e( 'Manage preferences for your travel plans and collaboration.', 'traveler' ); ?></p>
        <?php if ( $settings_updated ) : ?>
            <div class="notice" role="status"><?php esc_html_e( 'Settings saved.', 'traveler' ); ?></div>
        <?php endif; ?>
        <section class="settings-section" aria-labelledby="import-tools-heading">
            <h2 id="import-tools-heading"><?php esc_html_e( 'Import tools', 'traveler' ); ?></h2>
            <dl class="integration-list">
                <div class="integration-item">
                    <dt><?php esc_html_e( 'AI-assisted parsing', 'traveler' ); ?></dt>
                    <dd><?php echo esc_html( $has_ai ? __( 'Available', 'traveler' ) : __( 'Not connected', 'traveler' ) ); ?></dd>
                    <p>
                        <?php echo esc_html( $has_ai
                            ? __( 'Traveler can extract itinerary details from plain-text confirmations.', 'traveler' )
                            : __( 'Calendar and basic parsing remain available for imports.', 'traveler' )
                        ); ?>
                    </p>
                </div>
                <div class="integration-item">
                    <dt><?php esc_html_e( 'AI Assistant', 'traveler' ); ?></dt>
                    <dd><?php echo esc_html( $has_ai_assistant ? __( 'Connected', 'traveler' ) : __( 'Not connected', 'traveler' ) ); ?></dd>
                    <p>
                        <?php echo esc_html( $has_ai_assistant
                            ? __( 'AI Assistant can work with your trips through Traveler abilities.', 'traveler' )
                            : __( 'Trip planning and editing remain available directly in Traveler.', 'traveler' )
                        ); ?>
                    </p>
                </div>
            </dl>
        </section>
        <section class="settings-section" aria-labelledby="delegation-settings-heading">
            <h2 id="delegation-settings-heading"><?php esc_html_e( 'Delegation', 'traveler' ); ?></h2>
            <form class="settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="traveler_update_user_settings">
                <?php wp_nonce_field( 'traveler_update_user_settings' ); ?>
                <label class="setting-option">
                    <input type="checkbox" name="allow_delegated_trip_creation" value="1" <?php checked( $allow_delegated_trip_creation ); ?>>
                    <span>
                        <strong><?php esc_html_e( 'Let other users create travel plans for me', 'traveler' ); ?></strong>
                        <span><?php esc_html_e( 'Other users on this WordPress site can create trips for you. They can edit those trips on your behalf, and you can change access later in each trip\'s settings.', 'traveler' ); ?></span>
                    </span>
                </label>
                <?php if ( $allow_delegated_trip_creation ) : ?>
                    <div class="setting-field">
                        <label for="delegated_trip_creation_capability"><?php esc_html_e( 'Minimum permission to create trips for me', 'traveler' ); ?></label>
                        <select id="delegated_trip_creation_capability" name="delegated_trip_creation_capability">
                            <?php foreach ( $delegation_capability_options as $capability => $label ) : ?>
                                <option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $delegated_trip_creation_capability, $capability ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="setting-help"><?php esc_html_e( 'Only users at this level or higher will see you as a Create for option.', 'traveler' ); ?></span>
                    </div>
                <?php endif; ?>
                <div class="setting-field">
                    <label for="global_trip_editor_capability"><?php esc_html_e( 'Who can modify my travel plans', 'traveler' ); ?></label>
                    <select id="global_trip_editor_capability" name="global_trip_editor_capability">
                        <option value="none" <?php selected( $global_trip_editor_capability, 'none' ); ?>><?php esc_html_e( 'Only me and trip editors', 'traveler' ); ?></option>
                        <?php foreach ( $delegation_capability_options as $capability => $label ) : ?>
                            <option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $global_trip_editor_capability, $capability ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="setting-help"><?php esc_html_e( 'Users at this level or higher can edit all of your trips. Per-trip editors can still be changed in each trip\'s settings.', 'traveler' ); ?></span>
                </div>
                <div class="actions">
                    <a href="<?php echo esc_url( home_url( '/traveler/' ) ); ?>"><?php esc_html_e( 'Back to Traveler', 'traveler' ); ?></a>
                    <button type="submit"><?php esc_html_e( 'Save Settings', 'traveler' ); ?></button>
                </div>
            </form>
        </section>
    </main>
    <?php wp_app_body_close(); ?>
</body>
</html>
