<?php

namespace TravelApp;

use WpApp\WpApp;
use WpApp\BaseApp;
use WpApp\BaseStorage;
use TravelApp\Parser\GenericParser;
use TravelApp\Parser\IcsParser;
use TravelApp\Parser\QuickPlanParser;

class App extends BaseApp {
    private static $instance = null;
    private $url_preview_service = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        self::$instance = $this;

        // See https://github.com/akirk/wp-app for documentation.
        $this->app = new WpApp( $this->get_template_dir(), $this->get_url_path(), [
            // Access control
            'require_login'      => true,
            'require_capability' => 'read',

            // Masterbar
            // 'show_masterbar_for_anonymous' => false,
            // 'show_wp_logo'                 => true,
            // 'show_site_name'               => true,
            // 'show_dark_mode_toggle'        => false,
            // 'clear_admin_bar'              => false,
            // 'add_app_node'                 => false,

            // App identity
            'app_name'     => 'Travel App',
            // 'my_apps'      => true,
            'my_apps_icon' => 'dashicons-location-alt',

            // Progressive Web App support
            'pwa'          => $this->get_pwa_config(),
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'admin_post_travel_app_import', [ $this, 'handle_import' ] );
        add_action( 'admin_post_travel_app_update_user_settings', [ $this, 'handle_update_user_settings' ] );
        add_action( 'admin_post_travel_app_update_trip', [ $this, 'handle_update_trip' ] );
        add_action( 'admin_post_travel_app_open_journal_entry', [ $this, 'handle_open_journal_entry' ] );
        add_action( 'admin_post_travel_app_prepare_journal_post', [ $this, 'handle_prepare_journal_post' ] );
        add_action( 'admin_post_travel_app_download_trip_html', [ $this, 'handle_download_trip_html' ] );
        add_action( 'wp_ajax_travel_app_generate_share_link', [ $this, 'handle_generate_share_link' ] );
        add_action( 'wp_ajax_travel_app_remove_share_link', [ $this, 'handle_remove_share_link' ] );
        add_action( 'wp_ajax_travel_app_clear_share_cache', [ $this, 'handle_clear_share_cache' ] );
        add_action( 'admin_post_travel_app_delete', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_travel_app_update_segment', [ $this, 'handle_update_segment' ] );
        add_action( 'admin_post_travel_app_add_segment', [ $this, 'handle_add_segment' ] );
        add_action( 'admin_post_travel_app_delete_segment', [ $this, 'handle_delete_segment' ] );
        add_action( 'admin_post_travel_app_upload_item_attachment', [ $this, 'handle_upload_item_attachment' ] );
        add_action( 'admin_post_travel_app_delete_item_attachment', [ $this, 'handle_delete_item_attachment' ] );
        // add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widgets' ] );
        add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
        add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
        add_filter( 'ai_assistant_ability_domains', [ $this, 'register_ai_assistant_ability_domains' ] );
        add_filter( 'ai_assistant_ability_instructions', [ $this, 'get_ai_assistant_ability_instructions' ], 10, 4 );
        add_filter( 'ai_assistant_welcome_tips', [ $this, 'register_ai_assistant_welcome_tips' ], 10, 2 );
        add_filter( 'map_meta_cap', [ $this, 'map_trip_meta_cap' ], 10, 4 );
        add_filter( 'wp_app_pwa_manifest_travel-app', [ $this, 'filter_pwa_manifest' ], 10, 2 );
        add_action( 'wp_app_head', [ $this, 'enqueue_assets' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_user_calendar' ], 0 );
        add_action( 'template_redirect', [ $this, 'maybe_render_shared_calendar' ], 0 );
        add_action( 'template_redirect', [ $this, 'maybe_render_shared_timeline' ], 0 );
    }

    protected function get_url_path(): string {
        return 'travel-app';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    private function get_pwa_config(): array {
        $asset_base_url = plugins_url( 'assets/', dirname( __DIR__ ) . '/travel-app.php' );
        $asset_path = (string) wp_parse_url( $asset_base_url, PHP_URL_PATH );
        $upload_dir = wp_upload_dir();
        $upload_path = ! empty( $upload_dir['baseurl'] ) ? (string) wp_parse_url( (string) $upload_dir['baseurl'], PHP_URL_PATH ) : '';

        return [
            'name'                             => 'Travel Timeline',
            'short_name'                       => 'Timeline',
            'manifest_path'                    => 'manifest.webmanifest',
            'service_worker_path'              => 'service-worker.js',
            'scope'                            => home_url( '/' ),
            'service_worker_allowed'           => '/',
            'background_color'                 => '#f8fafc',
            'theme_color'                      => '#0b6bcb',
            'icons'                            => [
                [
                    'src'   => plugins_url( 'assets/icon.svg', dirname( __DIR__ ) . '/travel-app.php' ),
                    'sizes' => 'any',
                    'type'  => 'image/svg+xml',
                ],
            ],
            'precache'                         => [
                plugins_url( 'assets/js/timeline-time.js', dirname( __DIR__ ) . '/travel-app.php' ),
                plugins_url( 'assets/js/offline-sync.js', dirname( __DIR__ ) . '/travel-app.php' ),
            ],
            'cache_name'                       => 'travel-app-v8',
            'cache_prefix'                     => 'travel-app-',
            'cacheable_paths'                  => array_values( array_filter( [
                $asset_path,
                $upload_path,
            ] ) ),
            'cacheable_search_params'          => [
                'travel_app_share=',
            ],
            'cache_message_type'               => 'travel-app-cache-url',
            'cache_status_message_type'        => 'travel-app-cache-status',
            'version_message_type'             => 'travel-app-version',
            'sync_tag'                         => 'travel-app-sync',
            'sync_message_type'                => 'travel-app-sync',
            'client_cache_selector'            => '[data-offline-cache-url]',
            'client_cache_url_attribute'       => 'data-offline-cache-url',
            'client_cache_available_attribute' => 'data-offline-available',
            'client_cache_status_event'        => 'travel-app-cache-status',
            'client_version_event'             => 'travel-app-version',
            'client_sync_event'                => 'travel-app-sync',
            'head_tags'                        => false,
        ];
    }

    public function enqueue_assets(): void {
        $script_path = dirname( __DIR__ ) . '/assets/js/timeline-time.js';
        $offline_script_path = dirname( __DIR__ ) . '/assets/js/offline-sync.js';

        wp_enqueue_script(
            'travel-app-timeline-time',
            plugins_url( 'assets/js/timeline-time.js', dirname( __DIR__ ) . '/travel-app.php' ),
            [],
            file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0',
            true
        );

        wp_enqueue_script(
            'travel-app-offline-sync',
            plugins_url( 'assets/js/offline-sync.js', dirname( __DIR__ ) . '/travel-app.php' ),
            [],
            file_exists( $offline_script_path ) ? (string) filemtime( $offline_script_path ) : '1.0.0',
            true
        );
        wp_add_inline_script(
            'travel-app-offline-sync',
            'window.travelAppPwa=' . wp_json_encode( [
                'messages' => [
                    'offlineQueued' => __( 'Saved offline. Changes will sync when you are back online.', 'travel-app' ),
                    'syncing'       => __( 'Syncing offline changes...', 'travel-app' ),
                    'synced'        => __( 'Offline changes synced.', 'travel-app' ),
                    'syncFailed'    => __( 'Some offline changes could not sync yet.', 'travel-app' ),
                ],
            ] ) . ';',
            'before'
        );
    }

    public function get_manifest_url( int $trip_id = 0, string $share_token = '' ): string {
        $args = [];
        if ( $trip_id > 0 ) {
            $args['trip_id'] = $trip_id;
        }
        if ( '' !== $share_token ) {
            $args['token'] = $share_token;
        }

        return $this->app->get_pwa_manifest_url( $args );
    }

    public function filter_pwa_manifest( array $manifest, array $config ): array {
        $trip_id = isset( $_GET['trip_id'] ) ? absint( $_GET['trip_id'] ) : 0;
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $manifest['name'] = __( 'Travel Timeline', 'travel-app' );
        $manifest['short_name'] = __( 'Timeline', 'travel-app' );
        $manifest['start_url'] = home_url( '/' . $this->get_url_path() . '/' );
        $manifest['scope'] = home_url( '/' );

        if ( $trip_id > 0 ) {
            $trip = Trip::get( $trip_id );
            if ( $trip ) {
                $manifest['name'] = $trip->title;
                $manifest['short_name'] = $this->get_manifest_short_name( $trip->title );
                $manifest['start_url'] = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );
                if ( '' !== $token ) {
                    $manifest['start_url'] = add_query_arg(
                        [
                            'travel_app_share' => $trip_id,
                            'travel_app_token' => $token,
                        ],
                        home_url( '/' )
                    );
                }
            }
        }

        return $manifest;
    }

    private function get_manifest_short_name( string $name ): string {
        $name = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $name ) ) );
        if ( '' === $name ) {
            return __( 'Timeline', 'travel-app' );
        }

        if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
            return mb_strlen( $name ) > 12 ? rtrim( mb_substr( $name, 0, 12 ) ) : $name;
        }

        return strlen( $name ) > 12 ? rtrim( substr( $name, 0, 12 ) ) : $name;
    }

    public function get_error_notice_message( string $error_code, string $fallback = '' ): string {
        $error_code = sanitize_key( $error_code );
        if ( '' === $error_code ) {
            return '' !== $fallback ? $fallback : __( 'The requested change could not be saved.', 'travel-app' );
        }

        $messages = [
            'attachment_delete_failed' => __( 'The attachment could not be deleted.', 'travel-app' ),
            'attachment_missing'       => __( 'Choose a file to upload.', 'travel-app' ),
            'attachment_not_found'     => __( 'This attachment could not be found.', 'travel-app' ),
            'attachment_too_large'     => __( 'Attachments must be 15 MB or smaller.', 'travel-app' ),
            'attachment_upload_failed' => __( 'The attachment could not be uploaded.', 'travel-app' ),
            'delete_failed'            => __( 'The travel plan could not be deleted.', 'travel-app' ),
            'delete_forbidden'         => __( 'This travel plan cannot be deleted.', 'travel-app' ),
            'edit_forbidden'           => __( 'This travel plan cannot be edited.', 'travel-app' ),
            'empty'                    => __( 'Paste itinerary text or upload a file to import.', 'travel-app' ),
            'empty_title'              => __( 'Travel plan title cannot be empty.', 'travel-app' ),
            'invalid_trip_owner'       => __( 'You cannot create travel plans for that user.', 'travel-app' ),
            'missing_itinerary_text'   => __( 'Paste itinerary text to import.', 'travel-app' ),
            'quick_plan_invalid'       => __( 'Review the parsed fields and choose where to save the item.', 'travel-app' ),
            'segment_delete_failed'    => __( 'This itinerary item could not be deleted.', 'travel-app' ),
            'segment_not_found'        => __( 'This itinerary item could not be found.', 'travel-app' ),
            'journal_create_failed'     => __( 'The journal entry could not be created.', 'travel-app' ),
            'journal_disabled'          => __( 'Travel journaling is disabled for this travel plan.', 'travel-app' ),
            'journal_invalid_date'      => __( 'Choose a valid day for the journal entry.', 'travel-app' ),
            'journal_not_found'         => __( 'This journal entry could not be found.', 'travel-app' ),
            'journal_post_failed'       => __( 'The journal post draft could not be prepared.', 'travel-app' ),
            'trip_not_found'           => __( 'This travel plan could not be found.', 'travel-app' ),
            'upload_failed'            => __( 'The itinerary file could not be uploaded.', 'travel-app' ),
            'upload_invalid'           => __( 'The itinerary file upload was invalid.', 'travel-app' ),
            'upload_read_failed'       => __( 'The itinerary file could not be read.', 'travel-app' ),
            'upload_too_large'         => __( 'The itinerary file is too large.', 'travel-app' ),
        ];

        if ( isset( $messages[ $error_code ] ) ) {
            return $messages[ $error_code ];
        }

        if ( '' === $fallback ) {
            $fallback = __( 'The requested change could not be saved.', 'travel-app' );
        }

        return sprintf(
            /* translators: 1: generic error notice, 2: technical error code. */
            __( '%1$s Error code: %2$s.', 'travel-app' ),
            $fallback,
            $error_code
        );
    }

    public function is_demo_mode_enabled(): bool {
        $enabled = defined( 'TRAVEL_APP_DEMO_MODE' ) && TRAVEL_APP_DEMO_MODE;

        return (bool) apply_filters( 'travel_app_demo_mode_enabled', $enabled );
    }

    /**
     * Build a Mask Private Data marker attribute, e.g. ' data-place-42-location'.
     *
     * The key should start with the database ID of the record the value belongs to, so the
     * same value keeps the same replacement across pages. Pass an empty key for values
     * rendered client-side, where the mask falls back to data-private-value instead.
     */
    public static function mask_attr( string $type, string $key = '' ): string {
        $type = sanitize_key( $type );
        if ( '' === $type ) {
            return '';
        }

        $key = trim( strtolower( (string) preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $key ) ), '-' );

        return ' data-' . $type . ( '' !== $key ? '-' . $key : '' );
    }

    public function is_playground(): bool {
        return function_exists( __NAMESPACE__ . '\is_playground' ) && is_playground();
    }

    private function get_url_preview_service(): UrlPreviewService {
        if ( null === $this->url_preview_service ) {
            $this->url_preview_service = new UrlPreviewService();
        }

        return $this->url_preview_service;
    }

    protected function setup_storage(): void {
        /*
         * Prefer WordPress-native storage before custom tables:
         * - Custom post types and post meta for content-like records.
         * - Taxonomies, terms, and term meta for shared categories or labels.
         * - User meta for per-user settings, preferences, and profile data.
         *
         * Use BaseStorage only when native entities do not fit, such as
         * high-volume rows, relational data, or non-content records.
         *
         * If you do need custom tables:
         *
         * class TravelAppStorage extends BaseStorage {
         *     protected function get_schema() {
         *         $charset_collate = $this->wpdb->get_charset_collate();
         *         return [
         *             "CREATE TABLE {$this->wpdb->prefix}travel_app_items (
         *                 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
         *                 user_id bigint(20) unsigned NOT NULL,
         *                 title varchar(255) NOT NULL,
         *                 created_at datetime DEFAULT CURRENT_TIMESTAMP,
         *                 PRIMARY KEY (id),
         *                 KEY user_id (user_id)
         *             ) $charset_collate;",
         *         ];
         *     }
         * }
         *
         * Then in __construct(): $this->storage = new TravelAppStorage();
         * And in activate():     $this->storage->create_tables();
         */
    }

    protected function setup_database(): void {
        $this->setup_storage();
    }

    protected function setup_routes(): void {
        $this->app->route( 'trip/{id}', 'trip.php' );
        $this->app->route( 'trip/{id}/map', 'map.php' );
        $this->app->route( 'settings', 'settings.php' );
    }

    protected function setup_menu(): void {
        $current_trip = $this->get_masterbar_current_trip();
        if ( $current_trip ) {
            $this->app->add_menu_item(
                'trip-' . $current_trip->id,
                $this->get_masterbar_trip_label( $current_trip ),
                home_url( '/' . $this->get_url_path() . '/trip/' . $current_trip->id . '/' )
            );
        }

        $this->app->add_menu_item( 'settings', __( 'Settings', 'travel-app' ), home_url( '/' . $this->get_url_path() . '/settings/' ) );
    }

    private function get_masterbar_current_trip(): ?Trip {
        if ( ! is_user_logged_in() ) {
            return null;
        }

        $today = current_time( 'Y-m-d' );
        $current_trips = array_values( array_filter( Trip::for_current_user(), static function( Trip $trip ) use ( $today ): bool {
            return $trip->starts_at && $trip->starts_at <= $today && ( '' === $trip->ends_at || $trip->ends_at >= $today );
        } ) );

        if ( empty( $current_trips ) ) {
            return null;
        }

        usort( $current_trips, static function( Trip $a, Trip $b ): int {
            if ( $a->starts_at !== $b->starts_at ) {
                return strcmp( $a->starts_at, $b->starts_at );
            }

            return strcmp( $a->title, $b->title );
        } );

        return $current_trips[0];
    }

    private function get_masterbar_trip_label( Trip $trip ): string {
        $title = '' !== trim( $trip->title ) ? $trip->title : __( 'Travel Plan', 'travel-app' );
        $date = $trip->starts_at ? substr( $trip->starts_at, 5 ) : '';
        $label = '' !== $date ? $date . ' ' . $title : $title;

        return strlen( $label ) > 38 ? rtrim( substr( $label, 0, 35 ) ) . '...' : $label;
    }

    public function register_post_types(): void {
        $translate_labels = did_action( 'init' );

        register_post_type( 'travel_app_item', [
            'labels'       => [
                'name'          => $translate_labels ? __( 'Itinerary Items', 'travel-app' ) : 'Itinerary Items',
                'singular_name' => $translate_labels ? __( 'Itinerary Item', 'travel-app' ) : 'Itinerary Item',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'author' ],
            'map_meta_cap' => true,
        ] );

        register_post_type( 'travel_app_journal', [
            'labels'       => [
                'name'                     => $translate_labels ? __( 'Travel Journals', 'travel-app' ) : 'Travel Journals',
                'singular_name'            => $translate_labels ? __( 'Travel Journal', 'travel-app' ) : 'Travel Journal',
                'edit_item'                => $translate_labels ? __( 'Edit Travel Journal', 'travel-app' ) : 'Edit Travel Journal',
                'publish_item'             => $translate_labels ? __( 'Save Journal', 'travel-app' ) : 'Save Journal',
                'item_published'           => $translate_labels ? __( 'Journal saved.', 'travel-app' ) : 'Journal saved.',
                'item_published_privately' => $translate_labels ? __( 'Journal saved privately.', 'travel-app' ) : 'Journal saved privately.',
                'item_updated'             => $translate_labels ? __( 'Journal updated.', 'travel-app' ) : 'Journal updated.',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'author', 'revisions' ],
            'map_meta_cap' => true,
        ] );
    }

    public static function require_login_for_rest( $result, $server, $request ) {
        if ( is_user_logged_in() ) {
            return $result;
        }

        if ( 0 === strpos( $request->get_route(), '/wp/v2/travel_app_trip' ) ) {
            return new \WP_Error(
                'rest_login_required',
                __( 'Authentication is required to read this data.', 'travel-app' ),
                [ 'status' => rest_authorization_required_code() ]
            );
        }

        return $result;
    }

    public function register_taxonomies(): void {
        $translate_labels = did_action( 'init' );

        // travel_app_trip is show_in_rest (needed for the editor), so core would
        // serve trip names to anonymous callers over /wp/v2/travel_app_trip.
        // Gate it via wp-app's Access: single-trip reads are checked as
        // read_travel_app_trip WITH the trip id, so map_trip_meta_cap (owner,
        // editor, or valid share token) applies to REST too; the listing needs a
        // coarse cap (login). Older wp-app without Access -> request filter.
        $rest_gate = class_exists( '\\WpApp\\Rest\\Access' );
        if ( ! $rest_gate ) {
            add_filter( 'rest_pre_dispatch', [ __CLASS__, 'require_login_for_rest' ], 10, 3 );
        }

        register_taxonomy( 'travel_app_trip', 'travel_app_item', [
            'labels'            => [
                'name'          => $translate_labels ? __( 'Travel Plans', 'travel-app' ) : 'Travel Plans',
                'singular_name' => $translate_labels ? __( 'Travel Plan', 'travel-app' ) : 'Travel Plan',
            ],
            'public'            => false,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'rest_controller_class' => $rest_gate ? \WpApp\Rest\Access::protect_taxonomy( 'travel_app_trip', 'read_travel_app_trip', 'read' ) : null,
            'show_admin_column' => true,
        ] );
    }

    public function map_trip_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
        if ( ! in_array( $cap, [ 'read_travel_app_trip', 'edit_travel_app_trip', 'delete_travel_app_trip' ], true ) ) {
            return $caps;
        }

        $trip_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
        if ( $trip_id <= 0 ) {
            return [ 'do_not_allow' ];
        }

        $trip = Trip::get( $trip_id );
        if ( ! $trip ) {
            return [ 'do_not_allow' ];
        }

        if ( 'read_travel_app_trip' === $cap && $this->request_has_trip_share_token( $trip_id ) ) {
            return [ 'exist' ];
        }

        if ( $trip->owner_id() === $user_id ) {
            return [ 'read' ];
        }

        if ( in_array( $cap, [ 'read_travel_app_trip', 'edit_travel_app_trip' ], true ) && $this->is_trip_editor( $trip_id, $user_id ) ) {
            return [ 'read' ];
        }

        if ( in_array( $cap, [ 'read_travel_app_trip', 'edit_travel_app_trip' ], true ) && $this->user_can_edit_trips_for_owner( $user_id, $trip->owner_id() ) ) {
            return [ 'read' ];
        }

        return [ 'do_not_allow' ];
    }

    public function get_delegation_capability_options(): array {
        return [
            'read'              => __( 'Any logged-in user', 'travel-app' ),
            'edit_posts'        => __( 'Contributors and above', 'travel-app' ),
            'publish_posts'     => __( 'Authors and above', 'travel-app' ),
            'edit_others_posts' => __( 'Editors and above', 'travel-app' ),
            'manage_options'    => __( 'Administrators only', 'travel-app' ),
        ];
    }

    private function normalize_delegation_capability( string $capability, string $fallback = 'read' ): string {
        $capability = sanitize_key( $capability );
        $options = $this->get_delegation_capability_options();

        return isset( $options[ $capability ] ) ? $capability : $fallback;
    }

    public function get_delegated_trip_creation_capability( int $owner_user_id ): string {
        return $this->normalize_delegation_capability(
            (string) get_user_meta( $owner_user_id, '_travel_app_delegated_trip_creation_capability', true ),
            'edit_others_posts'
        );
    }

    public function get_global_trip_editor_capability( int $owner_user_id ): string {
        $capability = sanitize_key( (string) get_user_meta( $owner_user_id, '_travel_app_global_trip_editor_capability', true ) );

        if ( 'none' === $capability || '' === $capability ) {
            return 'none';
        }

        return $this->normalize_delegation_capability( $capability, 'none' );
    }

    private function user_has_delegation_capability( int $actor_user_id, string $capability ): bool {
        return $actor_user_id > 0 && 'none' !== $capability && user_can( $actor_user_id, $capability );
    }

    public function user_allows_delegated_trip_creation( int $owner_user_id, ?int $actor_user_id = null ): bool {
        if ( $owner_user_id <= 0 || '1' !== (string) get_user_meta( $owner_user_id, '_travel_app_allow_delegated_trip_creation', true ) ) {
            return false;
        }

        if ( null === $actor_user_id ) {
            return true;
        }

        return $this->user_has_delegation_capability( $actor_user_id, $this->get_delegated_trip_creation_capability( $owner_user_id ) );
    }

    public function user_can_edit_trips_for_owner( int $actor_user_id, int $owner_user_id ): bool {
        if ( $actor_user_id <= 0 || $owner_user_id <= 0 || $actor_user_id === $owner_user_id ) {
            return false;
        }

        return $this->user_has_delegation_capability( $actor_user_id, $this->get_global_trip_editor_capability( $owner_user_id ) );
    }

    public function get_global_editor_owner_ids_for_user( ?int $actor_user_id = null ): array {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        if ( $actor_user_id <= 0 ) {
            return [];
        }

        $owner_ids = [];
        foreach ( get_users( [ 'fields' => 'ids' ] ) as $owner_user_id ) {
            $owner_user_id = (int) $owner_user_id;
            if ( $this->user_can_edit_trips_for_owner( $actor_user_id, $owner_user_id ) ) {
                $owner_ids[] = $owner_user_id;
            }
        }

        return array_values( array_unique( $owner_ids ) );
    }

    public function get_delegated_trip_owner_options( ?int $actor_user_id = null ): array {
        $actor_user_id = $actor_user_id ?? get_current_user_id();
        if ( $actor_user_id <= 0 ) {
            return [];
        }

        $options = [];
        $actor = get_user_by( 'id', $actor_user_id );
        if ( $actor ) {
            $options[] = $actor;
        }

        $delegating_users = get_users( [
            'fields'     => 'all',
            'exclude'    => [ $actor_user_id ],
            'meta_key'   => '_travel_app_allow_delegated_trip_creation',
            'meta_value' => '1',
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ] );

        foreach ( $delegating_users as $user ) {
            if ( $user instanceof \WP_User && $this->user_allows_delegated_trip_creation( (int) $user->ID, $actor_user_id ) ) {
                $options[] = $user;
            }
        }

        return $options;
    }

    private function resolve_import_owner_id(): int {
        $actor_user_id = get_current_user_id();
        $owner_user_id = isset( $_POST['travel_app_owner_user_id'] ) ? absint( $_POST['travel_app_owner_user_id'] ) : $actor_user_id;

        if ( $owner_user_id === $actor_user_id ) {
            return $actor_user_id;
        }

        return $this->user_allows_delegated_trip_creation( $owner_user_id, $actor_user_id ) ? $owner_user_id : 0;
    }

    public function get_trip_editor_ids( int $trip_id ): array {
        $raw_ids = get_term_meta( $trip_id, '_travel_app_editor_user_ids', false );
        if ( 1 === count( $raw_ids ) && is_array( $raw_ids[0] ) ) {
            $raw_ids = $raw_ids[0];
        }

        $ids = array_filter( array_map( 'absint', (array) $raw_ids ) );
        $owner_id = Trip::get_owner_id( $trip_id );

        return array_values( array_diff( array_unique( $ids ), [ $owner_id ] ) );
    }

    public function is_trip_editor( int $trip_id, int $user_id ): bool {
        return $user_id > 0 && in_array( $user_id, $this->get_trip_editor_ids( $trip_id ), true );
    }

    public function current_user_can_manage_trip_editors( int $trip_id ): bool {
        return get_current_user_id() > 0 && Trip::get_owner_id( $trip_id ) === get_current_user_id();
    }

    public function get_trip_editor_candidates( int $trip_id ): array {
        $owner_id = Trip::get_owner_id( $trip_id );
        if ( $owner_id <= 0 ) {
            return [];
        }

        return get_users( [
            'fields'  => 'all',
            'exclude' => [ $owner_id ],
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ] );
    }

    public function get_trip_traveller_label( array $trip_data ): string {
        $owner_id = absint( $trip_data['owner_id'] ?? 0 );
        if ( $owner_id <= 0 || $owner_id === get_current_user_id() ) {
            return '';
        }

        $owner = get_user_by( 'id', $owner_id );
        $display_name = $owner ? trim( (string) $owner->display_name ) : '';

        return sprintf(
            /* translators: %s: travel plan owner display name. */
            __( 'Traveller: %s', 'travel-app' ),
            '' !== $display_name ? $display_name : __( 'another user', 'travel-app' )
        );
    }

    private function update_trip_editors( int $trip_id, array $editor_ids ) {
        if ( ! $this->current_user_can_manage_trip_editors( $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $owner_id = Trip::get_owner_id( $trip_id );
        $editor_ids = array_values( array_diff( array_unique( array_filter( array_map( 'absint', $editor_ids ) ) ), [ $owner_id ] ) );

        delete_term_meta( $trip_id, '_travel_app_editor_user_ids' );
        foreach ( $editor_ids as $editor_id ) {
            if ( get_user_by( 'id', $editor_id ) ) {
                add_term_meta( $trip_id, '_travel_app_editor_user_ids', $editor_id, false );
            }
        }

        return true;
    }

    private function request_has_trip_share_token( int $trip_id ): bool {
        $shared_trip_id = isset( $_GET['travel_app_share'] ) ? absint( $_GET['travel_app_share'] ) : 0;
        if ( $shared_trip_id !== $trip_id ) {
            return false;
        }

        $token = isset( $_GET['travel_app_token'] ) ? sanitize_text_field( wp_unslash( $_GET['travel_app_token'] ) ) : '';

        return '' !== $this->get_trip_share_mode_by_token( $trip_id, $token );
    }

    public function register_dashboard_widgets(): void {
        /*
         * Register dashboard widgets here. This method runs on
         * wp_dashboard_setup.
         *
         * wp_add_dashboard_widget(
         *     'travel_app_dashboard',
         *     'Travel App',
         *     [ $this, 'render_dashboard_widget' ]
         * );
         */
    }

    public function render_dashboard_widget(): void {
        /*
         * echo esc_html__( 'Add your dashboard summary here.', 'travel-app' );
         */
    }

    public function register_ability_category(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }

        wp_register_ability_category( 'travel-app', [
            'label'       => __( 'Travel App', 'travel-app' ),
            'description' => __( 'Abilities for managing pasted travel itineraries.', 'travel-app' ),
        ] );
    }

    public function register_abilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }

        wp_register_ability( 'travel-app/list-trips', [
            'label'               => __( 'List Travel Plans', 'travel-app' ),
            'description'         => 'Returns the current user\'s saved travel plans with IDs, dates, and segment counts.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'active' => [
                        'type'        => 'boolean',
                        'description' => 'When true, return only trips active today. When false, return only trips not active today.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'trips' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'           => [ 'type' => 'integer', 'description' => 'Use with travel-app/get-trip.' ],
                                'title'        => [ 'type' => 'string' ],
                                'starts_at'    => [ 'type' => 'string' ],
                                'ends_at'      => [ 'type' => 'string' ],
                                'is_active'    => [ 'type' => 'boolean' ],
                                'segment_count'=> [ 'type' => 'integer' ],
                            ],
                        ],
                    ],
                ],
            ],
            'execute_callback'    => [ $this, 'list_ability_items' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Present travel plans as a compact summary. Use returned IDs for follow-up detail calls.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/import-itinerary', [
            'label'               => __( 'Import Pasted Itinerary', 'travel-app' ),
            'description'         => 'Parses pasted booking confirmation text or itinerary email text and saves it as a structured travel plan for the current user.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'itinerary_text' => [
                        'type'        => 'string',
                        'description' => 'Raw copied itinerary, booking confirmation, reservation email, or travel plan text.',
                    ],
                ],
                'required'             => [ 'itinerary_text' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'             => [ 'type' => 'integer' ],
                    'title'          => [ 'type' => 'string' ],
                    'starts_at'      => [ 'type' => 'string' ],
                    'ends_at'        => [ 'type' => 'string' ],
                    'segment_count'  => [ 'type' => 'integer' ],
                    'segments'       => ItineraryItem::array_schema(),
                    'parser'         => [ 'type' => 'string' ],
                    'parser_error'   => Trip::parser_error_schema(),
                    'missing_fields' => Trip::missing_fields_schema(),
                    'url'            => [ 'type' => 'string' ],
                    'share_urls'     => Trip::share_urls_schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'import_ability_itinerary' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'After importing, summarize the created travel plan and include the app URL for review. If missing_fields or parser_error is present, report which fields were not filled and why.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/create-travel-plan', [
            'label'               => __( 'Create Travel Plan', 'travel-app' ),
            'description'         => 'Creates a new, empty travel plan for the current user from a title and optional dates, without parsing any itinerary text. Add itinerary items afterwards with travel-app/add-itinerary-item.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'title'     => [
                        'type'        => 'string',
                        'description' => 'Travel plan title, for example the destination or occasion.',
                    ],
                    'starts_at' => [
                        'type'        => 'string',
                        'description' => 'Optional first day of the trip as YYYY-MM-DD.',
                    ],
                    'ends_at'   => [
                        'type'        => 'string',
                        'description' => 'Optional last day of the trip as YYYY-MM-DD. Defaults to starts_at when omitted.',
                    ],
                ],
                'required'             => [ 'title' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'created' => [ 'type' => 'boolean' ],
                    'trip'    => Trip::schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'create_ability_trip' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this when the user wants a new trip but has no booking text to import. Do not invent dates; leave them out unless the user gave them. Return the Travel App URL afterwards.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/get-trip', [
            'label'               => __( 'Get Travel Plan', 'travel-app' ),
            'description'         => 'Returns full details for one saved travel plan owned by the current user, including itinerary items, attachments, existing share links, and app URLs.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips.',
                    ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'title'         => [ 'type' => 'string' ],
                    'starts_at'     => [ 'type' => 'string' ],
                    'ends_at'       => [ 'type' => 'string' ],
                    'segment_count' => [ 'type' => 'integer' ],
                    'segments'      => ItineraryItem::array_schema(),
                    'url'           => [ 'type' => 'string' ],
                    'share_urls'    => Trip::share_urls_schema(),
                    'parser'        => [ 'type' => 'string' ],
                    'parser_error'  => Trip::parser_error_schema(),
                    'missing_fields' => Trip::missing_fields_schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'get_ability_trip' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this before editing or deleting itinerary items so item IDs and current values are known. When summarizing, group items by date and call out missing dates, times, or locations.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/get-itinerary-item', [
            'label'               => __( 'Get Itinerary Item', 'travel-app' ),
            'description'         => 'Returns one structured itinerary item owned by the current user, including item URLs, attachments, and fields useful for cross-app handoff.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'trip_id' => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips or travel-app/get-trip.',
                    ],
                    'item_id' => [
                        'type'        => 'integer',
                        'description' => 'Itinerary item ID from the trip segments returned by travel-app/get-trip.',
                    ],
                ],
                'required'             => [ 'trip_id', 'item_id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'trip_id' => [ 'type' => 'integer' ],
                    'item'    => ItineraryItem::schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'get_ability_segment' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this when the user refers to one itinerary item and another app needs its structured fields. For flights, map title to flight-log/save-flight flightnr, location to from, end_location to to, and date/time to date.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/review-trip-fields', [
            'label'               => __( 'Review Missing Itinerary Fields', 'travel-app' ),
            'description'         => 'Reports blank itinerary fields for a saved travel plan, including parser error details when available.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips or travel-app/get-trip.',
                    ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'             => [ 'type' => 'integer' ],
                    'title'          => [ 'type' => 'string' ],
                    'parser'         => [ 'type' => 'string' ],
                    'parser_error'   => Trip::parser_error_schema(),
                    'missing_fields' => Trip::missing_fields_schema(),
                    'url'            => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'review_ability_trip_fields' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Report each missing field with the itinerary item it belongs to and the reason returned by the ability. Include the Travel App URL for review.',
                    'readonly'     => true,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/update-travel-plan', [
            'label'               => __( 'Rename Travel Plan', 'travel-app' ),
            'description'         => 'Renames one travel plan owned by the current user.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id'    => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips.',
                    ],
                    'title' => [
                        'type'        => 'string',
                        'description' => 'New travel plan title.',
                    ],
                ],
                'required'             => [ 'id', 'title' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'updated' => [ 'type' => 'boolean' ],
                    'trip'    => Trip::schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'update_ability_trip' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this when the user asks to rename or retitle a travel plan. Return the updated Travel App link.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/add-itinerary-item', [
            'label'               => __( 'Add Itinerary Item', 'travel-app' ),
            'description'         => 'Adds a flight, lodging, train, car, activity, or other itinerary item to an existing travel plan owned by the current user.',
            'category'            => 'travel-app',
            'input_schema'        => $this->get_itinerary_item_ability_input_schema( true ),
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'added'   => [ 'type' => 'boolean' ],
                    'item_id' => [ 'type' => 'integer' ],
                    'item'    => ItineraryItem::schema(),
                    'trip'    => Trip::schema(),
                    'url'     => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'add_ability_segment' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this for adding one new reservation or plan to an existing trip. If the target trip is ambiguous, list or get trips first and ask the user to choose.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/update-itinerary-item', [
            'label'               => __( 'Update Itinerary Item', 'travel-app' ),
            'description'         => 'Updates selected fields on one itinerary item owned by the current user. Omitted item fields keep their existing values.',
            'category'            => 'travel-app',
            'input_schema'        => $this->get_itinerary_item_ability_input_schema( false ),
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'updated' => [ 'type' => 'boolean' ],
                    'item_id' => [ 'type' => 'integer' ],
                    'item'    => ItineraryItem::schema(),
                    'trip'    => Trip::schema(),
                    'url'     => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'update_ability_segment' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Call travel-app/get-trip first unless the item ID and existing item values are already known. Preserve fields the user did not ask to change.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/delete-itinerary-item', [
            'label'               => __( 'Delete Itinerary Item', 'travel-app' ),
            'description'         => 'Moves one itinerary item owned by the current user to the trash.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'trip_id' => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips or travel-app/get-trip.',
                    ],
                    'item_id' => [
                        'type'        => 'integer',
                        'description' => 'Itinerary item ID from the trip segments returned by travel-app/get-trip.',
                    ],
                ],
                'required'             => [ 'trip_id', 'item_id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'deleted' => [ 'type' => 'boolean' ],
                    'item_id' => [ 'type' => 'integer' ],
                    'trip'    => Trip::schema(),
                ],
            ],
            'execute_callback'    => [ $this, 'delete_ability_segment' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Confirm the exact itinerary item before deleting when the request is ambiguous. Use get-trip first to map user-visible item descriptions to item IDs.',
                    'readonly'     => false,
                    'destructive'  => true,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/create-share-link', [
            'label'               => __( 'Create Travel Plan Share Link', 'travel-app' ),
            'description'         => 'Creates or returns an existing read-only timeline share link for one travel plan owned by the current user.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id'   => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips.',
                    ],
                    'mode' => [
                        'type'        => 'string',
                        'enum'        => [ 'fellow', 'public' ],
                        'description' => 'Use fellow for private sharing with travel companions, public for a more public read-only link.',
                        'default'     => 'fellow',
                    ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'   => [ 'type' => 'integer' ],
                    'mode' => [ 'type' => 'string' ],
                    'url'  => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'create_ability_share_link' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Use this when the user asks to share a trip or create a read-only timeline link. Tell the user it is read-only.',
                    'readonly'     => false,
                    'destructive'  => false,
                    'idempotent'   => true,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/remove-share-link', [
            'label'               => __( 'Remove Travel Plan Share Link', 'travel-app' ),
            'description'         => 'Removes a read-only share link for one travel plan owned by the current user.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id'   => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips.',
                    ],
                    'mode' => [
                        'type'        => 'string',
                        'enum'        => [ 'fellow', 'public' ],
                        'description' => 'Which share link to remove.',
                        'default'     => 'fellow',
                    ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'removed' => [ 'type' => 'boolean' ],
                    'id'      => [ 'type' => 'integer' ],
                    'mode'    => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'remove_ability_share_link' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Confirm the exact travel plan and share mode before removing a link when the request is ambiguous.',
                    'readonly'     => false,
                    'destructive'  => true,
                    'idempotent'   => false,
                ],
            ],
        ] );

        wp_register_ability( 'travel-app/delete-travel-plan', [
            'label'               => __( 'Delete Travel Plan', 'travel-app' ),
            'description'         => 'Deletes one saved travel plan owned by the current user and moves its itinerary items to the trash.',
            'category'            => 'travel-app',
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'description' => 'Travel plan ID from travel-app/list-trips.',
                    ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'deleted' => [ 'type' => 'boolean' ],
                    'id'      => [ 'type' => 'integer' ],
                ],
            ],
            'execute_callback'    => [ $this, 'delete_ability_trip' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'Confirm the exact travel plan with the user before deleting when the request is ambiguous.',
                    'readonly'     => false,
                    'destructive'  => true,
                    'idempotent'   => false,
                ],
            ],
        ] );
    }

    public function list_ability_items( $input ): array {
        $active = is_array( $input ) && array_key_exists( 'active', $input ) ? (bool) $input['active'] : null;
        $trips = array_map( static function( Trip $trip ): array {
            return $trip->to_array();
        }, Trip::for_current_user() );

        if ( null !== $active ) {
            $trips = array_values( array_filter( $trips, static function( array $trip ) use ( $active ): bool {
                return (bool) ( $trip['is_active'] ?? false ) === $active;
            } ) );
        }

        return [
            'trips' => $trips,
        ];
    }

    private function get_itinerary_item_ability_input_schema( bool $creating ): array {
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'trip_id' => [
                    'type'        => 'integer',
                    'description' => 'Travel plan ID from travel-app/list-trips or travel-app/get-trip.',
                ],
                'segment' => ItineraryItem::input_schema(),
            ],
            'required'             => [ 'trip_id', 'segment' ],
            'additionalProperties' => false,
        ];

        if ( ! $creating ) {
            $schema['properties']['item_id'] = [
                'type'        => 'integer',
                'description' => 'Itinerary item ID from the trip segments returned by travel-app/get-trip.',
            ];
            $schema['required'] = [ 'trip_id', 'item_id', 'segment' ];
        }

        return $schema;
    }

    public function register_ai_assistant_ability_domains( array $domains ): array {
        $domains['travel-app'] = 'Travel App, itinerary, travel plans, trips, trip timeline, flights, lodging, hotels, trains, rental cars, activities, booking confirmations, reservations, travel organizer, share trip';
        return $domains;
    }

    public function get_ai_assistant_ability_instructions( string $instructions, string $ability_id, $args, $result ): string {
        if ( 'travel-app/import-itinerary' === $ability_id && ! empty( $result['id'] ) ) {
            $instructions = 'Tell the user the travel plan was saved. Summarize title, dates, and travel segments, then link to the Travel App URL if present. If missing_fields or parser_error is present, report which fields were not filled and why.';
        } elseif ( 'travel-app/create-travel-plan' === $ability_id && ! empty( $result['trip']['url'] ) ) {
            $instructions = 'Tell the user the empty travel plan was created, include the Travel App URL, and offer to add itinerary items to it.';
        } elseif ( in_array( $ability_id, [ 'travel-app/add-itinerary-item', 'travel-app/update-itinerary-item', 'travel-app/delete-itinerary-item', 'travel-app/update-travel-plan' ], true ) && ! empty( $result['trip']['url'] ) ) {
            $instructions = 'Tell the user what changed and include the Travel App URL for review.';
        } elseif ( 'travel-app/create-share-link' === $ability_id && ! empty( $result['url'] ) ) {
            $instructions = 'Tell the user the read-only travel timeline share link is ready and include the URL.';
        } elseif ( 'travel-app/get-trip' === $ability_id && ! empty( $result['id'] ) ) {
            $instructions = 'Summarize the travel plan by date. Use missing_fields and parser_error to mention which itinerary fields are blank and why.';
        } elseif ( 'travel-app/review-trip-fields' === $ability_id && ! empty( $result['id'] ) ) {
            $instructions = 'Report each missing itinerary field with the item it belongs to and the reason returned by the ability. If no missing fields are returned, say the saved fields look complete.';
        }

        return $instructions;
    }

    public function register_ai_assistant_welcome_tips( array $tips, array $context ): array {
        $tips['travel-app'] = [
            __( 'Paste a booking confirmation and ask me to add it to Travel App.', 'travel-app' ),
            __( 'Ask me to summarize, update, or share one of your saved travel plans.', 'travel-app' ),
        ];

        return $tips;
    }

    public function import_ability_itinerary( $input ) {
        $input = is_array( $input ) ? $input : [];
        $text  = isset( $input['itinerary_text'] ) ? (string) $input['itinerary_text'] : '';

        if ( '' === trim( $text ) ) {
            return new \WP_Error( 'missing_itinerary_text', __( 'Paste itinerary text to import.', 'travel-app' ) );
        }

        $parsed = $this->parse_itinerary_text( $text );
        $trip_id = $this->save_trip( $parsed, $text );

        if ( is_wp_error( $trip_id ) ) {
            return $trip_id;
        }

        $trip = Trip::from_term( $trip_id );
        return $trip ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [];
    }

    public function create_ability_trip( $input ) {
        $input = is_array( $input ) ? $input : [];
        $title = sanitize_text_field( isset( $input['title'] ) ? (string) $input['title'] : '' );

        if ( '' === $title ) {
            return new \WP_Error( 'missing_title', __( 'Enter a title for the travel plan.', 'travel-app' ) );
        }

        $dates = [];
        foreach ( [ 'starts_at', 'ends_at' ] as $key ) {
            $value = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
            if ( '' !== $value && ! $this->is_valid_ability_date( $value ) ) {
                return new \WP_Error( 'invalid_date', __( 'Dates must be given as YYYY-MM-DD.', 'travel-app' ) );
            }
            $dates[ $key ] = $value;
        }

        if ( '' === $dates['ends_at'] ) {
            $dates['ends_at'] = $dates['starts_at'];
        }

        if ( '' === $dates['starts_at'] && '' !== $dates['ends_at'] ) {
            $dates['starts_at'] = $dates['ends_at'];
        }

        if ( $dates['ends_at'] < $dates['starts_at'] ) {
            return new \WP_Error( 'invalid_date_range', __( 'The end date must not be before the start date.', 'travel-app' ) );
        }

        $trip_id = $this->save_trip( [
            'title'        => $title,
            'starts_at'    => $dates['starts_at'],
            'ends_at'      => $dates['ends_at'],
            'segments'     => [],
            'parser'       => 'manual',
            'parser_error' => [],
        ], '' );

        if ( is_wp_error( $trip_id ) ) {
            return $trip_id;
        }

        return [
            'created' => true,
            'trip'    => ( $trip = Trip::from_term( $trip_id ) ) ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [],
        ];
    }

    private function is_valid_ability_date( string $value ): bool {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return false;
        }

        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
    }

    public function get_ability_trip( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $term = Trip::get( $trip_id );

        if ( ! $term || ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'trip_not_found', __( 'This travel plan could not be found.', 'travel-app' ) );
        }

        return $term->to_ability_array( [ $this, 'get_trip_share_url' ] );
    }

    public function get_ability_segment( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['trip_id'] ) ? absint( $input['trip_id'] ) : 0;
        $item_id = isset( $input['item_id'] ) ? absint( $input['item_id'] ) : 0;
        $item = ItineraryItem::get_user_item( $trip_id, $item_id );
        $segment = $item ? $item->to_array() : null;

        if ( ! $segment ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        return [
            'trip_id' => $trip_id,
            'item'    => $segment,
        ];
    }

    public function review_ability_trip_fields( $input ) {
        $trip = $this->get_ability_trip( $input );
        if ( is_wp_error( $trip ) ) {
            return $trip;
        }

        return [
            'id'             => (int) ( $trip['id'] ?? 0 ),
            'title'          => (string) ( $trip['title'] ?? '' ),
            'parser'         => (string) ( $trip['parser'] ?? '' ),
            'parser_error'   => isset( $trip['parser_error'] ) && is_array( $trip['parser_error'] ) ? $trip['parser_error'] : [],
            'missing_fields' => isset( $trip['missing_fields'] ) && is_array( $trip['missing_fields'] ) ? $trip['missing_fields'] : [],
            'url'            => (string) ( $trip['url'] ?? '' ),
        ];
    }

    public function update_ability_trip( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $title = isset( $input['title'] ) ? (string) $input['title'] : '';
        $updated = $this->update_user_trip_title( $trip_id, $title );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return [
            'updated' => true,
            'trip'    => ( $trip = Trip::from_term( $trip_id ) ) ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [],
        ];
    }

    public function add_ability_segment( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['trip_id'] ) ? absint( $input['trip_id'] ) : 0;
        $segment = isset( $input['segment'] ) && is_array( $input['segment'] ) ? $input['segment'] : [];
        $item_id = $this->add_user_trip_segment( $trip_id, $segment );

        if ( is_wp_error( $item_id ) ) {
            return $item_id;
        }

        return [
            'added'   => true,
            'item_id' => (int) $item_id,
            'item'    => ( $item = ItineraryItem::get_user_item( $trip_id, (int) $item_id ) ) ? $item->to_array() : [],
            'trip'    => ( $trip = Trip::from_term( $trip_id ) ) ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [],
            'url'     => home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/#segment-' . (int) $item_id ),
        ];
    }

    public function update_ability_segment( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['trip_id'] ) ? absint( $input['trip_id'] ) : 0;
        $item_id = isset( $input['item_id'] ) ? absint( $input['item_id'] ) : 0;
        $current_item = ItineraryItem::get_user_item( $trip_id, $item_id );
        $current = $current_item ? $current_item->to_array() : null;

        if ( ! $current ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        $changes = isset( $input['segment'] ) && is_array( $input['segment'] ) ? $input['segment'] : [];
        $segment = array_merge( $current, $changes );
        $updated = $this->update_user_trip_segment( $trip_id, $item_id, $segment );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return [
            'updated' => true,
            'item_id' => $item_id,
            'item'    => ( $item = ItineraryItem::get_user_item( $trip_id, $item_id ) ) ? $item->to_array() : [],
            'trip'    => ( $trip = Trip::from_term( $trip_id ) ) ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [],
            'url'     => home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/#segment-' . $item_id ),
        ];
    }

    public function delete_ability_segment( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['trip_id'] ) ? absint( $input['trip_id'] ) : 0;
        $item_id = isset( $input['item_id'] ) ? absint( $input['item_id'] ) : 0;
        $deleted = $this->delete_user_trip_segment( $trip_id, $item_id );

        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }

        return [
            'deleted' => true,
            'item_id' => $item_id,
            'trip'    => ( $trip = Trip::from_term( $trip_id ) ) ? $trip->to_ability_array( [ $this, 'get_trip_share_url' ] ) : [],
        ];
    }

    public function create_ability_share_link( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $mode = isset( $input['mode'] ) ? (string) $input['mode'] : 'fellow';
        $token = $this->create_trip_share_token( $trip_id, $mode );

        if ( '' === $token ) {
            return new \WP_Error( 'share_forbidden', __( 'This travel plan cannot be shared.', 'travel-app' ) );
        }

        $this->clear_trip_public_cache( $trip_id );
        $mode = $this->normalize_share_mode( $mode );

        return [
            'id'   => $trip_id,
            'mode' => $mode,
            'url'  => $this->get_trip_share_url( $trip_id, $mode ),
        ];
    }

    public function remove_ability_share_link( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $mode = isset( $input['mode'] ) ? (string) $input['mode'] : 'fellow';

        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'share_forbidden', __( 'This travel plan cannot be updated.', 'travel-app' ) );
        }

        $mode = $this->normalize_share_mode( $mode );
        $this->clear_trip_public_cache( $trip_id );
        delete_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ) );

        return [
            'removed' => true,
            'id'      => $trip_id,
            'mode'    => $mode,
        ];
    }

    public function delete_ability_trip( $input ) {
        $input = is_array( $input ) ? $input : [];
        $trip_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $deleted = $this->delete_user_trip( $trip_id );

        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }

        return [
            'deleted' => true,
            'id'      => $trip_id,
        ];
    }

    public function handle_import(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to import travel plans.', 'travel-app' ), 403 );
        }

        check_admin_referer( 'travel_app_import' );

        $import_trip_id = isset( $_POST['import_trip_id'] ) ? absint( $_POST['import_trip_id'] ) : 0;
        $redirect = $import_trip_id
            ? home_url( '/' . $this->get_url_path() . '/trip/' . $import_trip_id . '/' )
            : home_url( '/' . $this->get_url_path() . '/' );
        if ( $import_trip_id && ! current_user_can( 'edit_travel_app_trip', $import_trip_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'edit_forbidden', home_url( '/' . $this->get_url_path() . '/' ) ) );
            exit;
        }

        $owner_user_id = $import_trip_id ? Trip::get_owner_id( $import_trip_id ) : $this->resolve_import_owner_id();
        if ( $owner_user_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'invalid_trip_owner', $redirect ) );
            exit;
        }

        $draft_key = isset( $_POST['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_POST['quick_plan_draft'] ) ) : '';
        if ( '' !== $draft_key ) {
            $target = isset( $_POST['quick_plan_target'] ) ? sanitize_text_field( wp_unslash( $_POST['quick_plan_target'] ) ) : '';
            $this->save_quick_plan_draft_submission( $draft_key, $target, $redirect, $owner_user_id );
        }

        $text = isset( $_POST['itinerary_text'] ) ? (string) wp_unslash( $_POST['itinerary_text'] ) : '';
        $file_text = $this->get_uploaded_itinerary_text();
        if ( is_wp_error( $file_text ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $file_text->get_error_code() ), $redirect ) );
            exit;
        }

        if ( '' !== trim( $file_text ) ) {
            $text = '' !== trim( $text ) ? $text . "\n\n" . $file_text : $file_text;
        }

        if ( '' === trim( $text ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'empty', $redirect ) );
            exit;
        }

        $parsed = $this->parse_itinerary_text( $text );

        if ( $import_trip_id ) {
            $segment = 1 === count( $parsed['segments'] ?? [] ) ? ( $parsed['segments'][0] ?? [] ) : [];
            if ( empty( $segment ) || empty( $segment['date'] ) ) {
                wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
                exit;
            }

            $draft_key = $this->store_quick_plan_draft( [
                'text'       => sanitize_text_field( wp_unslash( $text ) ),
                'segment'    => $segment,
                'matches'    => [],
                'trip_title' => $this->get_quick_plan_trip_title( $segment ),
                'parser'     => (string) ( $parsed['parser'] ?? 'fallback' ),
                'parser_error' => $parsed['parser_error'] ?? [],
                'target_trip_id' => $import_trip_id,
            ] );

            wp_safe_redirect( add_query_arg( 'quick_plan_draft', rawurlencode( $draft_key ), $redirect ) );
            exit;
        }

        if ( '' === trim( $file_text ) && 1 === count( $parsed['segments'] ?? [] ) ) {
            $segment = $parsed['segments'][0] ?? [];

            if ( '' !== $segment['date'] ) {
                $matches = $this->find_quick_plan_trip_matches( $segment, $text );
                if ( ! empty( $matches ) ) {
                    $draft_key = $this->store_quick_plan_draft( [
                        'text'       => sanitize_text_field( wp_unslash( $text ) ),
                        'segment'    => $segment,
                        'matches'    => $matches,
                        'trip_title' => $this->get_quick_plan_trip_title( $segment ),
                        'parser'     => (string) ( $parsed['parser'] ?? 'fallback' ),
                        'parser_error' => $parsed['parser_error'] ?? [],
                    ] );

                    wp_safe_redirect( add_query_arg( 'quick_plan_draft', rawurlencode( $draft_key ), $redirect ) );
                    exit;
                }

                if ( 'quick-plan' === (string) ( $parsed['parser'] ?? '' ) ) {
                    $trip_id = $this->save_trip( $parsed, $text, $owner_user_id );

                    if ( is_wp_error( $trip_id ) ) {
                        wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $trip_id->get_error_code() ), $redirect ) );
                        exit;
                    }

                    wp_safe_redirect( add_query_arg( 'imported', rawurlencode( (string) $trip_id ), $redirect ) );
                    exit;
                }
            }
        }
        $trip_id = $this->save_trip( $parsed, $text, $owner_user_id );

        if ( is_wp_error( $trip_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $trip_id->get_error_code() ), $redirect ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'imported', rawurlencode( (string) $trip_id ), $redirect ) );
        exit;
    }

    public function handle_update_user_settings(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to update Travel App settings.', 'travel-app' ), 403 );
        }

        check_admin_referer( 'travel_app_update_user_settings' );

        update_user_meta(
            get_current_user_id(),
            '_travel_app_allow_delegated_trip_creation',
            isset( $_POST['allow_delegated_trip_creation'] ) ? '1' : '0'
        );
        update_user_meta(
            get_current_user_id(),
            '_travel_app_delegated_trip_creation_capability',
            $this->normalize_delegation_capability(
                isset( $_POST['delegated_trip_creation_capability'] ) ? (string) wp_unslash( $_POST['delegated_trip_creation_capability'] ) : 'edit_others_posts',
                'edit_others_posts'
            )
        );
        update_user_meta(
            get_current_user_id(),
            '_travel_app_global_trip_editor_capability',
            $this->normalize_delegation_capability(
                isset( $_POST['global_trip_editor_capability'] ) ? (string) wp_unslash( $_POST['global_trip_editor_capability'] ) : 'none',
                'none'
            )
        );

        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = home_url( '/' . $this->get_url_path() . '/settings/' );
        }

        wp_safe_redirect( add_query_arg( 'settings_updated', '1', $redirect ) );
        exit;
    }

    private function save_quick_plan_draft_submission( string $draft_key, string $target, string $redirect, int $owner_user_id ): void {
        $draft = $this->get_quick_plan_draft( $draft_key );
        if ( empty( $draft ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
            exit;
        }

        $segment = ItineraryItem::from_request();
        if ( empty( $segment ) || empty( $segment['date'] ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
            exit;
        }

        if ( 'existing' === $target ) {
            $target = isset( $_POST['quick_plan_existing_trip'] ) ? (string) absint( $_POST['quick_plan_existing_trip'] ) : '';
            if ( '' === $target || '0' === $target ) {
                wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
                exit;
            }
        }

        if ( 'new' === $target || '' === $target ) {
            $trip_title = isset( $_POST['quick_plan_trip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quick_plan_trip_title'] ) ) : '';
            $trip_id = $this->save_trip( [
                'title'     => '' !== trim( $trip_title ) ? $trip_title : $this->get_quick_plan_trip_title( $segment ),
                'starts_at' => (string) $segment['date'],
                'ends_at'   => (string) ( $segment['end_date'] ?: $segment['date'] ),
                'segments'  => [ $segment ],
                'parser'    => sanitize_key( (string) ( $draft['parser'] ?? 'quick-plan' ) ),
            ], (string) ( $draft['text'] ?? '' ), $owner_user_id );
            $item_id = 0;
        } else {
            $trip_id = absint( $target );
            $item_id = $this->add_user_trip_segment( $trip_id, $segment );
        }

        if ( '' !== $draft_key ) {
            delete_transient( $this->get_quick_plan_transient_name( $draft_key ) );
        }

        if ( is_wp_error( $trip_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $trip_id->get_error_code() ), $redirect ) );
            exit;
        }

        if ( is_wp_error( $item_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $item_id->get_error_code() ), $redirect ) );
            exit;
        }

        $trip_url = home_url( '/' . $this->get_url_path() . '/trip/' . absint( $trip_id ) . '/' );
        if ( $item_id ) {
            $trip_url = add_query_arg( 'updated', rawurlencode( (string) $item_id ), $trip_url ) . '#segment-' . $item_id;
        } else {
            $trip_url = add_query_arg( 'imported', rawurlencode( (string) $trip_id ), $trip_url );
        }

        wp_safe_redirect( $trip_url );
        exit;
    }

    public function handle_delete(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to delete travel plans.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_delete_' . $trip_id );

        $redirect = home_url( '/' . $this->get_url_path() . '/' );
        $deleted = $this->delete_user_trip( $trip_id );

        if ( is_wp_error( $deleted ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $deleted->get_error_code() ), $redirect ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'deleted', rawurlencode( (string) $trip_id ), $redirect ) );
        exit;
    }

    public function maybe_render_shared_timeline(): void {
        $trip_id = isset( $_GET['travel_app_share'] ) ? absint( $_GET['travel_app_share'] ) : 0;
        $token = isset( $_GET['travel_app_token'] ) ? sanitize_text_field( wp_unslash( $_GET['travel_app_token'] ) ) : '';

        if ( $trip_id <= 0 || '' === $token ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        global $wp_app_route;
        $wp_app_route = [
            'app_path' => $this->get_url_path(),
            'pattern'  => 'share',
            'template' => 'trip.php',
            'params'   => [
                'id'    => (string) $trip_id,
                'token' => $token,
            ],
        ];

        $travel_app_shared_timeline = true;
        include $this->get_template_dir() . '/trip.php';
        exit;
    }

    public function maybe_render_shared_calendar(): void {
        $trip_id = isset( $_GET['travel_app_calendar'] ) ? absint( $_GET['travel_app_calendar'] ) : 0;
        $token = isset( $_GET['travel_app_token'] ) ? sanitize_text_field( wp_unslash( $_GET['travel_app_token'] ) ) : '';

        if ( $trip_id <= 0 || '' === $token ) {
            return;
        }

        $mode = $this->get_trip_share_mode_by_token( $trip_id, $token );
        if ( '' === $mode ) {
            wp_die(
                esc_html__( 'This calendar could not be found.', 'travel-app' ),
                esc_html__( 'Calendar not found', 'travel-app' ),
                [ 'response' => 404 ]
            );
        }

        $trip = Trip::get( $trip_id );
        if ( ! $trip ) {
            wp_die(
                esc_html__( 'This travel plan could not be found.', 'travel-app' ),
                esc_html__( 'Travel plan not found', 'travel-app' ),
                [ 'response' => 404 ]
            );
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        $ics = $this->render_trip_ics( $trip_id, $mode );
        $filename = sanitize_title( $trip->title );
        if ( '' === $filename ) {
            $filename = 'travel-plan-' . $trip_id;
        }
        if ( 'public' === $mode ) {
            $filename .= '-public';
        }

        nocache_headers();
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="' . $filename . '.ics"' );
        header( 'Content-Length: ' . strlen( $ics ) );

        echo $ics;
        exit;
    }

    public function maybe_render_user_calendar(): void {
        $user_id = isset( $_GET['travel_app_trips_calendar'] ) ? absint( $_GET['travel_app_trips_calendar'] ) : 0;
        $token = isset( $_GET['travel_app_token'] ) ? sanitize_text_field( wp_unslash( $_GET['travel_app_token'] ) ) : '';

        if ( $user_id <= 0 || '' === $token ) {
            return;
        }

        if ( ! $this->user_calendar_token_matches( $user_id, $token ) ) {
            wp_die(
                esc_html__( 'This calendar could not be found.', 'travel-app' ),
                esc_html__( 'Calendar not found', 'travel-app' ),
                [ 'response' => 404 ]
            );
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        $user = get_user_by( 'id', $user_id );
        $display_name = $user ? trim( (string) $user->display_name ) : '';
        $calendar_name = '' !== $display_name
            ? sprintf(
                /* translators: %s: user display name. */
                __( '%s Travel Plans', 'travel-app' ),
                $display_name
            )
            : __( 'Travel Plans', 'travel-app' );
        $ics = $this->render_user_trips_ics( $user_id, $calendar_name );

        nocache_headers();
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="travel-plans.ics"' );
        header( 'Content-Length: ' . strlen( $ics ) );

        echo $ics;
        exit;
    }

    public function handle_update_trip(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to edit travel plans.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_update_trip_' . $trip_id );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );
        $title = isset( $_POST['trip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['trip_title'] ) ) : '';
        $updated = $this->update_user_trip_title( $trip_id, $title );

        if ( ! is_wp_error( $updated ) && isset( $_POST['trip_show_now_next_present'] ) ) {
            $updated = $this->update_user_trip_now_next_visibility( $trip_id, isset( $_POST['trip_show_now_next'] ) );
        }

        if ( ! is_wp_error( $updated ) && isset( $_POST['trip_journal_enabled_present'] ) ) {
            $updated = $this->update_user_trip_journal_visibility( $trip_id, isset( $_POST['trip_journal_enabled'] ) );
        }

        if ( ! is_wp_error( $updated ) && isset( $_POST['trip_journal_publishing_defaults_present'] ) ) {
            $category_id = isset( $_POST['trip_journal_category_id'] ) ? absint( $_POST['trip_journal_category_id'] ) : 0;
            $tags = isset( $_POST['trip_journal_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['trip_journal_tags'] ) ) : '';
            $updated = $this->update_user_trip_journal_publishing_defaults( $trip_id, $category_id, $tags );
        }

        if ( ! is_wp_error( $updated ) && isset( $_POST['trip_editors_present'] ) ) {
            $editor_ids = isset( $_POST['trip_editor_ids'] ) && is_array( $_POST['trip_editor_ids'] )
                ? array_map( 'absint', wp_unslash( $_POST['trip_editor_ids'] ) )
                : [];
            $updated = $this->update_trip_editors( $trip_id, $editor_ids );
        }

        if ( is_wp_error( $updated ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $updated->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'trip_updated', rawurlencode( (string) $trip_id ), $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_open_journal_entry(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to edit travel journals.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_open_journal_entry_' . $trip_id );

        $date = isset( $_POST['journal_date'] ) ? sanitize_text_field( wp_unslash( $_POST['journal_date'] ) ) : '';
        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );
        $journal_id = $this->get_or_create_journal_entry( $trip_id, $date );

        if ( is_wp_error( $journal_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $journal_id->get_error_code() ), $redirect ) );
            exit;
        }

        $edit_link = get_edit_post_link( (int) $journal_id, 'raw' );
        if ( ! $edit_link ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'journal_create_failed', $redirect ) );
            exit;
        }

        wp_safe_redirect( $edit_link );
        exit;
    }

    public function get_journal_entries_for_trip( int $trip_id ): array {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return [];
        }

        $entries = [];
        $journal_ids = get_posts( [
            'post_type'      => 'travel_app_journal',
            'post_status'    => [ 'draft', 'private', 'publish', 'future', 'pending' ],
            'author'         => get_current_user_id(),
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_travel_app_trip_id',
            'meta_value'     => (string) $trip_id,
        ] );

        foreach ( $journal_ids as $journal_id ) {
            $date = (string) get_post_meta( (int) $journal_id, '_travel_app_date', true );
            if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                continue;
            }

            $post_id = absint( get_post_meta( (int) $journal_id, '_travel_app_published_post_id', true ) );

            $entries[ $date ] = [
                'id'      => (int) $journal_id,
                'post_id' => $post_id,
            ];
        }

        return $entries;
    }

    public function handle_prepare_journal_post(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to prepare travel journal posts.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $journal_id = isset( $_POST['journal_id'] ) ? absint( $_POST['journal_id'] ) : 0;
        check_admin_referer( 'travel_app_prepare_journal_post_' . $trip_id . '_' . $journal_id );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );
        $post_id = $this->prepare_journal_post_draft( $trip_id, $journal_id );

        if ( is_wp_error( $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $post_id->get_error_code() ), $redirect ) );
            exit;
        }

        $edit_link = get_edit_post_link( (int) $post_id, 'raw' );
        if ( ! $edit_link ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'journal_post_failed', $redirect ) );
            exit;
        }

        wp_safe_redirect( $edit_link );
        exit;
    }

    public function handle_download_trip_html(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to download travel plans.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_GET['trip_id'] ) ? absint( $_GET['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_download_trip_html_' . $trip_id );

        $trip = Trip::get( $trip_id );
        if ( ! $trip || ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
            wp_die(
                esc_html__( 'This travel plan could not be found.', 'travel-app' ),
                esc_html__( 'Travel plan not found', 'travel-app' ),
                [ 'response' => 404 ]
            );
        }

        $mode = isset( $_GET['share_mode'] ) ? sanitize_key( wp_unslash( $_GET['share_mode'] ) ) : 'fellow';
        $mode = $this->normalize_share_mode( $mode );
        $html = $this->render_static_trip_html( $trip_id, $mode );
        $filename = sanitize_title( $trip->title );
        if ( '' === $filename ) {
            $filename = 'travel-plan-' . $trip_id;
        }
        if ( 'public' === $mode ) {
            $filename .= '-public';
        }

        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.html"' );
        header( 'Content-Length: ' . strlen( $html ) );

        echo $html;
        exit;
    }

    public function handle_generate_share_link(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in to share travel plans.', 'travel-app' ) ], 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $mode = isset( $_POST['share_mode'] ) ? sanitize_key( wp_unslash( $_POST['share_mode'] ) ) : 'fellow';
        check_ajax_referer( 'travel_app_share_link_' . $trip_id, 'nonce' );

        if ( '' === $this->create_trip_share_token( $trip_id, $mode ) ) {
            wp_send_json_error( [ 'message' => __( 'This travel plan cannot be shared.', 'travel-app' ) ], 404 );
        }

        $this->clear_trip_public_cache( $trip_id );

        wp_send_json_success( [
            'mode'         => $this->normalize_share_mode( $mode ),
            'url'          => $this->get_trip_share_url( $trip_id, $mode ),
            'calendar_url' => $this->get_trip_calendar_url( $trip_id, $mode ),
            'message'      => __( 'Read-only timeline share link generated.', 'travel-app' ),
        ] );
    }

    public function handle_remove_share_link(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in to update travel plan sharing.', 'travel-app' ) ], 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $mode = isset( $_POST['share_mode'] ) ? sanitize_key( wp_unslash( $_POST['share_mode'] ) ) : 'fellow';
        check_ajax_referer( 'travel_app_share_link_' . $trip_id, 'nonce' );

        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            wp_send_json_error( [ 'message' => __( 'This travel plan cannot be updated.', 'travel-app' ) ], 404 );
        }

        $this->clear_trip_public_cache( $trip_id );
        delete_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ) );

        wp_send_json_success( [
            'mode'         => $this->normalize_share_mode( $mode ),
            'url'          => '',
            'calendar_url' => '',
            'message'      => __( 'Read-only timeline share link removed.', 'travel-app' ),
        ] );
    }

    public function handle_clear_share_cache(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in to refresh shared travel plans.', 'travel-app' ) ], 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_ajax_referer( 'travel_app_share_link_' . $trip_id, 'nonce' );

        if ( ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
            wp_send_json_error( [ 'message' => __( 'This travel plan cannot be refreshed.', 'travel-app' ) ], 404 );
        }

        $this->clear_trip_public_cache( $trip_id );

        wp_send_json_success( [
            'urls'    => [
                'fellow' => $this->get_trip_share_url( $trip_id, 'fellow' ),
                'public' => $this->get_trip_share_url( $trip_id, 'public' ),
            ],
            'calendar_urls' => [
                'fellow' => $this->get_trip_calendar_url( $trip_id, 'fellow' ),
                'public' => $this->get_trip_calendar_url( $trip_id, 'public' ),
            ],
            'message' => __( 'Read-only timeline cache refreshed.', 'travel-app' ),
        ] );
    }

    public function handle_update_segment(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to edit itinerary items.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $index = isset( $_POST['segment_index'] ) ? absint( $_POST['segment_index'] ) : 0;
        check_admin_referer( 'travel_app_update_segment_' . $trip_id . '_' . $index );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' ) . '#segment-' . $index;
        $segment = ItineraryItem::from_request();

        $updated = $this->update_user_trip_segment( $trip_id, $index, $segment );
        if ( is_wp_error( $updated ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $updated->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg(
                'updated',
                rawurlencode( (string) $index ),
                home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' )
            ) . '#segment-' . $index;
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_add_segment(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to add itinerary items.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_add_segment_' . $trip_id );

        $segment = ItineraryItem::from_request();
        $added_item_id = $this->add_user_trip_segment( $trip_id, $segment );
        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );

        if ( is_wp_error( $added_item_id ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $added_item_id->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'updated', rawurlencode( (string) $added_item_id ), $redirect . '#segment-' . $added_item_id );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_delete_segment(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to delete itinerary items.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $index = isset( $_POST['segment_index'] ) ? absint( $_POST['segment_index'] ) : 0;
        check_admin_referer( 'travel_app_delete_segment_' . $trip_id . '_' . $index );

        $deleted = $this->delete_user_trip_segment( $trip_id, $index );
        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );

        if ( is_wp_error( $deleted ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $deleted->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'item_deleted', rawurlencode( (string) $index ), $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_upload_item_attachment(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to upload itinerary item attachments.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $index = isset( $_POST['segment_index'] ) ? absint( $_POST['segment_index'] ) : 0;
        check_admin_referer( 'travel_app_upload_item_attachment_' . $trip_id . '_' . $index );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' ) . '#segment-' . $index;
        $uploaded = $this->upload_user_trip_item_attachments( $trip_id, $index );

        if ( is_wp_error( $uploaded ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $uploaded->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'attachment_uploaded', rawurlencode( (string) $uploaded ), $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_delete_item_attachment(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to delete itinerary item attachments.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $index = isset( $_POST['segment_index'] ) ? absint( $_POST['segment_index'] ) : 0;
        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
        check_admin_referer( 'travel_app_delete_item_attachment_' . $trip_id . '_' . $index . '_' . $attachment_id );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' ) . '#segment-' . $index;
        $deleted = $this->delete_user_trip_item_attachment( $trip_id, $index, $attachment_id );

        if ( is_wp_error( $deleted ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $deleted->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'attachment_deleted', rawurlencode( (string) $attachment_id ), $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    private function delete_user_trip( int $trip_id ) {
        $term = Trip::get( $trip_id );

        if ( ! $term || ! current_user_can( 'delete_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'delete_forbidden', __( 'This travel plan cannot be deleted.', 'travel-app' ) );
        }

        $this->clear_trip_public_cache( $trip_id );

        foreach ( ItineraryItem::get_for_trip( $trip_id ) as $item ) {
            wp_trash_post( $item->id );
        }

        foreach ( $this->get_journal_entry_ids_for_trip( $trip_id ) as $journal_id ) {
            wp_trash_post( $journal_id );
        }

        $deleted = wp_delete_term( $trip_id, 'travel_app_trip' );
        if ( ! $deleted || is_wp_error( $deleted ) ) {
            return new \WP_Error( 'delete_failed', __( 'The travel plan could not be deleted.', 'travel-app' ) );
        }

        return true;
    }

    private function get_journal_entry_ids_for_trip( int $trip_id ): array {
        return array_map( 'intval', get_posts( [
            'post_type'      => 'travel_app_journal',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_key'       => '_travel_app_trip_id',
            'meta_value'     => (string) $trip_id,
        ] ) );
    }

    private function update_user_trip_now_next_visibility( int $trip_id, bool $show_now_next ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        update_term_meta( $trip_id, '_travel_app_show_now_next', $show_now_next ? '1' : '0' );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function update_user_trip_journal_visibility( int $trip_id, bool $journal_enabled ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        update_term_meta( $trip_id, '_travel_app_journal_enabled', $journal_enabled ? '1' : '0' );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function update_user_trip_journal_publishing_defaults( int $trip_id, int $category_id, string $tags ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        if ( $category_id > 0 && ! term_exists( $category_id, 'category' ) ) {
            $category_id = 0;
        }

        update_term_meta( $trip_id, '_travel_app_journal_category_id', $category_id );
        update_term_meta( $trip_id, '_travel_app_journal_tags', $this->normalize_journal_tag_list( $tags ) );

        return true;
    }

    private function prepare_journal_post_draft( int $trip_id, int $journal_id ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $journal = get_post( $journal_id );
        if ( ! $journal || 'travel_app_journal' !== $journal->post_type || (int) $journal->post_author !== get_current_user_id() ) {
            return new \WP_Error( 'journal_not_found', __( 'This journal entry could not be found.', 'travel-app' ) );
        }

        if ( $trip_id !== absint( get_post_meta( $journal_id, '_travel_app_trip_id', true ) ) ) {
            return new \WP_Error( 'journal_not_found', __( 'This journal entry could not be found.', 'travel-app' ) );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'journal_post_failed', __( 'The journal post draft could not be prepared.', 'travel-app' ) );
        }

        $post_id = absint( get_post_meta( $journal_id, '_travel_app_published_post_id', true ) );
        $existing_post = $post_id > 0 ? get_post( $post_id ) : null;
        if ( ! $existing_post || 'post' !== $existing_post->post_type || (int) $existing_post->post_author !== get_current_user_id() ) {
            $post_id = 0;
        } elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error( 'journal_post_failed', __( 'The journal post draft could not be prepared.', 'travel-app' ) );
        } elseif ( 'trash' === $existing_post->post_status ) {
            $untrashed_post = wp_untrash_post( $post_id );
            if ( ! $untrashed_post ) {
                return new \WP_Error( 'journal_post_failed', __( 'The journal post draft could not be prepared.', 'travel-app' ) );
            }
        }

        $post_data = [
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
            'post_title'   => $journal->post_title,
            'post_content' => $journal->post_content,
        ];

        if ( $post_id > 0 ) {
            $post_data['ID'] = $post_id;
            $updated_post_id = wp_update_post( $post_data, true );
        } else {
            $post_data['post_status'] = 'draft';
            $updated_post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $updated_post_id ) || ! $updated_post_id ) {
            return new \WP_Error( 'journal_post_failed', __( 'The journal post draft could not be prepared.', 'travel-app' ) );
        }

        $post_id = (int) $updated_post_id;
        $date = (string) get_post_meta( $journal_id, '_travel_app_date', true );
        $category_id = absint( get_term_meta( $trip_id, '_travel_app_journal_category_id', true ) );
        if ( $category_id > 0 && term_exists( $category_id, 'category' ) ) {
            wp_set_post_categories( $post_id, [ $category_id ], true );
        }

        $tags = (string) get_term_meta( $trip_id, '_travel_app_journal_tags', true );
        if ( '' !== $tags ) {
            wp_set_post_tags( $post_id, $tags, true );
        }

        update_post_meta( $journal_id, '_travel_app_published_post_id', $post_id );
        update_post_meta( $post_id, '_travel_app_source_journal_id', $journal_id );
        update_post_meta( $post_id, '_travel_app_trip_id', $trip_id );
        update_post_meta( $post_id, '_travel_app_date', $date );

        return $post_id;
    }

    private function get_or_create_journal_entry( int $trip_id, string $date ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        if ( '1' !== (string) get_term_meta( $trip_id, '_travel_app_journal_enabled', true ) ) {
            return new \WP_Error( 'journal_disabled', __( 'Travel journaling is disabled for this travel plan.', 'travel-app' ) );
        }

        if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return new \WP_Error( 'journal_invalid_date', __( 'Choose a valid day for the journal entry.', 'travel-app' ) );
        }

        $existing = get_posts( [
            'post_type'      => 'travel_app_journal',
            'post_status'    => [ 'draft', 'private', 'publish', 'future', 'pending' ],
            'author'         => get_current_user_id(),
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_travel_app_trip_id',
                    'value' => (string) $trip_id,
                ],
                [
                    'key'   => '_travel_app_date',
                    'value' => $date,
                ],
            ],
        ] );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        $trip = Trip::get( $trip_id );
        if ( ! $trip ) {
            return new \WP_Error( 'trip_not_found', __( 'This travel plan could not be found.', 'travel-app' ) );
        }

        $journal_id = wp_insert_post( [
            'post_type'    => 'travel_app_journal',
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
            'post_title'   => sprintf(
                /* translators: 1: trip title, 2: journal date. */
                __( '%1$s Journal: %2$s', 'travel-app' ),
                $trip->title,
                $this->format_date_label( $date )
            ),
            'post_content' => $this->build_journal_entry_content( $trip_id, $date ),
        ], true );

        if ( is_wp_error( $journal_id ) ) {
            return new \WP_Error( 'journal_create_failed', __( 'The journal entry could not be created.', 'travel-app' ) );
        }

        update_post_meta( (int) $journal_id, '_travel_app_trip_id', $trip_id );
        update_post_meta( (int) $journal_id, '_travel_app_date', $date );

        return (int) $journal_id;
    }

    private function build_journal_entry_content( int $trip_id, string $date ): string {
        $segments = array_map( static function( ItineraryItem $item ): array {
            return $item->to_array();
        }, ItineraryItem::get_for_trip( $trip_id ) );
        $timeline_segments = LodgingCoverage::timeline_segments( $segments );
        $blocks = [];

        foreach ( $timeline_segments as $segment ) {
            if ( $date !== (string) ( $segment['date'] ?? '' ) ) {
                continue;
            }

            $title = trim( implode( ' ', array_filter( [
                (string) ( $segment['time'] ?? '' ),
                (string) ( $segment['title'] ?? '' ),
            ] ) ) );

            if ( '' === $title ) {
                $title = __( 'Untitled item', 'travel-app' );
            }

            $blocks[] = '<!-- wp:heading {"level":2} -->' . "\n"
                . '<h2>' . esc_html( $title ) . '</h2>' . "\n"
                . '<!-- /wp:heading -->';
        }

        if ( empty( $blocks ) ) {
            $blocks[] = '<!-- wp:paragraph -->' . "\n"
                . '<p>' . esc_html__( 'Journal notes for this day.', 'travel-app' ) . '</p>' . "\n"
                . '<!-- /wp:paragraph -->';
        }

        return implode( "\n\n", $blocks );
    }

    private function normalize_journal_tag_list( string $tags ): string {
        $tag_names = array_filter(
            array_map(
                static function( string $tag ): string {
                    return sanitize_text_field( trim( $tag ) );
                },
                explode( ',', $tags )
            ),
            static function( string $tag ): bool {
                return '' !== $tag;
            }
        );

        return implode( ', ', array_unique( $tag_names ) );
    }

    private function update_user_trip_title( int $trip_id, string $title ) {
        if ( '' === trim( $title ) ) {
            return new \WP_Error( 'empty_title', __( 'Travel plan title cannot be empty.', 'travel-app' ) );
        }

        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $updated = wp_update_term( $trip_id, 'travel_app_trip', [
            'name' => $title,
        ] );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function update_user_trip_segment( int $trip_id, int $index, array $segment ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item = ItineraryItem::get_user_item( $trip_id, $index );
        if ( ! $item ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        $segment = ItineraryItem::normalize( $segment );
        $updated = wp_update_post( [
            'ID'           => $item->id,
            'post_title'   => $segment['title'] ?: __( 'Untitled item', 'travel-app' ),
            'post_content' => $segment['details'],
        ], true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $this->update_item_meta( $item->id, $segment );
        $this->update_trip_bounds_from_items( $trip_id );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function add_user_trip_segment( int $trip_id, array $segment ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item_id = $this->create_trip_item( $trip_id, $segment );
        if ( is_wp_error( $item_id ) ) {
            return $item_id;
        }

        $this->update_trip_bounds_from_items( $trip_id );
        $this->clear_trip_public_cache( $trip_id );

        return $item_id;
    }

    private function delete_user_trip_segment( int $trip_id, int $index ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item = ItineraryItem::get_user_item( $trip_id, $index );
        if ( ! $item ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        $deleted = wp_trash_post( $item->id );
        if ( ! $deleted ) {
            return new \WP_Error( 'segment_delete_failed', __( 'This itinerary item could not be deleted.', 'travel-app' ) );
        }

        $this->update_trip_bounds_from_items( $trip_id );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function upload_user_trip_item_attachments( int $trip_id, int $index ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item = ItineraryItem::get_user_item( $trip_id, $index );
        if ( ! $item ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        if ( empty( $_FILES['item_attachment'] ) || ! is_array( $_FILES['item_attachment'] ) ) {
            return new \WP_Error( 'attachment_missing', __( 'Choose a file to upload.', 'travel-app' ) );
        }

        $files = $this->normalize_uploaded_files( $_FILES['item_attachment'] );
        if ( empty( $files ) ) {
            return new \WP_Error( 'attachment_missing', __( 'Choose a file to upload.', 'travel-app' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $uploaded = 0;
        $original_file = $_FILES['item_attachment'];

        foreach ( $files as $file ) {
            $error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            if ( UPLOAD_ERR_NO_FILE === $error ) {
                continue;
            }

            if ( UPLOAD_ERR_OK !== $error ) {
                $_FILES['item_attachment'] = $original_file;
                return new \WP_Error( 'attachment_upload_failed', __( 'The attachment could not be uploaded.', 'travel-app' ) );
            }

            $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
            if ( $size > 15 * 1024 * 1024 ) {
                $_FILES['item_attachment'] = $original_file;
                return new \WP_Error( 'attachment_too_large', __( 'Attachments must be 15 MB or smaller.', 'travel-app' ) );
            }

            $_FILES['item_attachment'] = $file;
            $attachment_id = media_handle_upload( 'item_attachment', $item->id );

            if ( is_wp_error( $attachment_id ) ) {
                $_FILES['item_attachment'] = $original_file;
                return $attachment_id;
            }

            wp_update_post( [
                'ID'          => (int) $attachment_id,
                'post_author' => get_current_user_id(),
            ] );
            $uploaded++;
        }

        $_FILES['item_attachment'] = $original_file;

        if ( 0 === $uploaded ) {
            return new \WP_Error( 'attachment_missing', __( 'Choose a file to upload.', 'travel-app' ) );
        }

        $this->clear_trip_public_cache( $trip_id );

        return $uploaded;
    }

    private function delete_user_trip_item_attachment( int $trip_id, int $index, int $attachment_id ) {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $attachment = ItineraryItem::get_user_attachment( $trip_id, $index, $attachment_id );
        if ( ! $attachment ) {
            return new \WP_Error( 'attachment_not_found', __( 'This attachment could not be found.', 'travel-app' ) );
        }

        $deleted = wp_delete_attachment( $attachment->ID );
        if ( ! $deleted ) {
            return new \WP_Error( 'attachment_delete_failed', __( 'This attachment could not be deleted.', 'travel-app' ) );
        }

        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function clear_trip_public_cache( int $trip_id ): void {
        if ( $trip_id <= 0 ) {
            return;
        }

        $has_share_token = '' !== (string) get_term_meta( $trip_id, '_travel_app_share_token', true )
            || '' !== (string) get_term_meta( $trip_id, '_travel_app_public_share_token', true );
        if ( ! $has_share_token ) {
            return;
        }

        if ( ! $this->load_wp_super_cache_functions() ) {
            return;
        }

        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache( get_current_blog_id() );
            return;
        }

        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            global $file_prefix;
            wp_cache_clean_cache( isset( $file_prefix ) ? (string) $file_prefix : 'wp-cache-' );
        }
    }

    private function load_wp_super_cache_functions(): bool {
        if ( function_exists( 'wp_cache_clear_cache' ) || function_exists( 'wp_cache_clean_cache' ) ) {
            return true;
        }

        $config_file = WP_CONTENT_DIR . '/wp-cache-config.php';
        if ( is_readable( $config_file ) ) {
            require_once $config_file;
        }

        $phase2_file = WP_CONTENT_DIR . '/plugins/wp-super-cache/wp-cache-phase2.php';
        if ( is_readable( $phase2_file ) ) {
            require_once $phase2_file;
        }

        if ( function_exists( 'wp_cache_clear_cache' ) || function_exists( 'wp_cache_clean_cache' ) ) {
            return true;
        }

        $plugin_file = WP_CONTENT_DIR . '/plugins/wp-super-cache/wp-cache.php';
        if ( ! function_exists( 'wp_cache_clean_cache' ) && is_readable( $plugin_file ) ) {
            require_once $plugin_file;
        }

        return function_exists( 'wp_cache_clear_cache' ) || function_exists( 'wp_cache_clean_cache' );
    }

    private function normalize_uploaded_files( array $file ): array {
        if ( ! isset( $file['name'] ) || ! is_array( $file['name'] ) ) {
            return [ $file ];
        }

        $files = [];
        foreach ( array_keys( $file['name'] ) as $index ) {
            $files[] = [
                'name'     => $file['name'][ $index ] ?? '',
                'type'     => $file['type'][ $index ] ?? '',
                'tmp_name' => $file['tmp_name'][ $index ] ?? '',
                'error'    => $file['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $file['size'][ $index ] ?? 0,
            ];
        }

        return $files;
    }

    private function update_trip_bounds_from_items( int $trip_id ): void {
        $dates = [];
        foreach ( ItineraryItem::get_for_trip( $trip_id ) as $item ) {
            $segment = $item->to_array();
            if ( ! empty( $segment['date'] ) ) {
                $dates[] = (string) $segment['date'];
            }
            if ( ! empty( $segment['end_date'] ) ) {
                $dates[] = (string) $segment['end_date'];
            }
        }

        sort( $dates );
        update_term_meta( $trip_id, '_travel_app_starts_at', $dates[0] ?? '' );
        update_term_meta( $trip_id, '_travel_app_ends_at', $dates ? end( $dates ) : '' );
    }

    private function get_uploaded_itinerary_text() {
        if ( empty( $_FILES['itinerary_file'] ) || ! is_array( $_FILES['itinerary_file'] ) ) {
            return '';
        }

        $file = $_FILES['itinerary_file'];
        $error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ( UPLOAD_ERR_NO_FILE === $error ) {
            return '';
        }

        if ( UPLOAD_ERR_OK !== $error ) {
            return new \WP_Error( 'upload_failed', __( 'The itinerary file could not be uploaded.', 'travel-app' ) );
        }

        $tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
            return new \WP_Error( 'upload_invalid', __( 'The itinerary file upload was invalid.', 'travel-app' ) );
        }

        $size = isset( $file['size'] ) ? (int) $file['size'] : 0;
        if ( $size > 2 * 1024 * 1024 ) {
            return new \WP_Error( 'upload_too_large', __( 'The itinerary file is too large.', 'travel-app' ) );
        }

        $contents = file_get_contents( $tmp_name );
        if ( false === $contents ) {
            return new \WP_Error( 'upload_read_failed', __( 'The itinerary file could not be read.', 'travel-app' ) );
        }

        return (string) $contents;
    }

    public function get_trip_share_url( int $trip_id, string $mode = 'fellow' ): string {
        $token = $this->get_trip_share_token( $trip_id, $mode );
        if ( '' === $token ) {
            return '';
        }

        return add_query_arg(
            [
                'travel_app_share' => $trip_id,
                'travel_app_token' => $token,
            ],
            home_url( '/' )
        );
    }

    public function get_trip_calendar_url( int $trip_id, string $mode = 'fellow' ): string {
        $token = $this->get_trip_share_token( $trip_id, $mode );
        if ( '' === $token ) {
            return '';
        }

        return add_query_arg(
            [
                'travel_app_calendar' => $trip_id,
                'travel_app_token'    => $token,
            ],
            home_url( '/' )
        );
    }

    public function get_user_calendar_url( int $user_id = 0, bool $create = false ): string {
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $user_id <= 0 || get_current_user_id() !== $user_id ) {
            return '';
        }

        $token = $this->get_user_calendar_token( $user_id );
        if ( '' === $token && $create ) {
            $token = $this->create_user_calendar_token( $user_id );
        }
        if ( '' === $token ) {
            return '';
        }

        return add_query_arg(
            [
                'travel_app_trips_calendar' => $user_id,
                'travel_app_token'          => $token,
            ],
            home_url( '/' )
        );
    }

    public function get_trip_share_mode_by_token( int $trip_id, string $token ): string {
        if ( ! Trip::get( $trip_id ) || '' === $token ) {
            return '';
        }

        foreach ( [ 'fellow', 'public' ] as $mode ) {
            $stored_token = (string) get_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ), true );
            if ( '' !== $stored_token && hash_equals( $stored_token, $token ) ) {
                return $mode;
            }
        }

        return '';
    }

    public function get_trip_html_download_url( int $trip_id, string $mode = 'fellow' ): string {
        if ( $trip_id <= 0 ) {
            return '';
        }

        $mode = $this->normalize_share_mode( $mode );

        return wp_nonce_url(
            admin_url( 'admin-post.php?action=travel_app_download_trip_html&trip_id=' . $trip_id . '&share_mode=' . $mode ),
            'travel_app_download_trip_html_' . $trip_id
        );
    }

    public function get_static_timeline_script(): string {
        $script_path = dirname( __DIR__ ) . '/assets/js/timeline-time.js';

        return is_readable( $script_path ) ? (string) file_get_contents( $script_path ) : '';
    }

    private function render_static_trip_html( int $trip_id, string $mode = 'fellow' ): string {
        global $wp_app_route;

        $previous_route = $wp_app_route ?? null;
        $wp_app_route = [
            'app_path' => $this->get_url_path(),
            'pattern'  => 'trip/{id}',
            'template' => 'trip.php',
            'params'   => [
                'id' => (string) $trip_id,
            ],
        ];

        $travel_app_static_download = true;
        $travel_app_static_share_mode = $this->normalize_share_mode( $mode );
        ob_start();
        include $this->get_template_dir() . '/trip.php';
        $html = (string) ob_get_clean();

        if ( null === $previous_route ) {
            unset( $wp_app_route );
        } else {
            $wp_app_route = $previous_route;
        }

        return $html;
    }

    private function render_trip_ics( int $trip_id, string $mode = 'fellow' ): string {
        $trip = Trip::get( $trip_id );
        if ( ! $trip ) {
            return '';
        }

        $mode = $this->normalize_share_mode( $mode );
        $segments_user_id = Trip::get_owner_id( $trip_id );
        $trip_data = $trip->with_segments_user_id( $segments_user_id )->to_array();
        $calendar_name = (string) ( $trip_data['title'] ?? __( 'Travel Plan', 'travel-app' ) );

        return $this->render_trips_ics( [ $trip_data ], $calendar_name, $mode, false );
    }

    private function render_user_trips_ics( int $user_id, string $calendar_name ): string {
        $trips = array_map( static function( Trip $trip ): array {
            return $trip->with_segments_user_id( $trip->owner_id() )->to_array();
        }, Trip::for_user( $user_id ) );

        return $this->render_trips_ics( $trips, $calendar_name, 'fellow', true );
    }

    private function render_trips_ics( array $trips, string $calendar_name, string $mode = 'fellow', bool $include_trip_title = false ): string {
        $mode = $this->normalize_share_mode( $mode );
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Travel App//Travel App//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escape_ics_text( '' !== trim( $calendar_name ) ? $calendar_name : __( 'Travel Plans', 'travel-app' ) ),
        ];

        foreach ( $trips as $trip_data ) {
            if ( ! is_array( $trip_data ) ) {
                continue;
            }

            $trip_id = absint( $trip_data['id'] ?? 0 );
            $trip_title = trim( (string) ( $trip_data['title'] ?? '' ) );
            foreach ( (array) ( $trip_data['segments'] ?? [] ) as $segment ) {
                if ( ! is_array( $segment ) || '' === (string) ( $segment['date'] ?? '' ) ) {
                    continue;
                }

                $event_times = $this->get_segment_ics_times( $segment );
                if ( empty( $event_times ) ) {
                    continue;
                }

                $uid_source = home_url( '/travel-app/trip/' . $trip_id . '/#segment-' . (int) ( $segment['id'] ?? 0 ) );
                $is_fellow_share = 'fellow' === $mode;
                $is_transport_segment = $this->is_transport_segment( $segment );
                $description_parts = $is_fellow_share ? array_filter( [
                    (string) ( $segment['details'] ?? '' ),
                    (string) ( $segment['url'] ?? '' ),
                ] ) : [];
                $location = ( $is_fellow_share || $is_transport_segment ) ? trim( (string) ( $segment['location'] ?? '' ) ) : '';
                $end_location = ( $is_fellow_share || $is_transport_segment ) ? trim( (string) ( $segment['end_location'] ?? '' ) ) : '';
                if ( '' !== $end_location && '' !== $location ) {
                    $location .= ' - ' . $end_location;
                } elseif ( '' !== $end_location ) {
                    $location = $end_location;
                }

                $summary = (string) ( $segment['title'] ?? __( 'Untitled item', 'travel-app' ) );
                if ( $include_trip_title && '' !== $trip_title ) {
                    $summary = $trip_title . ': ' . $summary;
                }

                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:' . $this->escape_ics_text( md5( $uid_source ) . '@travel-app' );
                $lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
                $lines[] = 'SUMMARY:' . $this->escape_ics_text( $summary );
                foreach ( $event_times as $event_time_line ) {
                    $lines[] = $event_time_line;
                }
                if ( '' !== $location ) {
                    $lines[] = 'LOCATION:' . $this->escape_ics_text( $location );
                }
                if ( ! empty( $description_parts ) ) {
                    $lines[] = 'DESCRIPTION:' . $this->escape_ics_text( implode( "\n\n", $description_parts ) );
                }
                if ( $is_fellow_share && '' !== (string) ( $segment['url'] ?? '' ) ) {
                    $lines[] = 'URL:' . $this->escape_ics_text( (string) $segment['url'] );
                }
                $lines[] = 'END:VEVENT';
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode( "\r\n", array_map( [ $this, 'fold_ics_line' ], $lines ) ) . "\r\n";
    }

    private function get_segment_ics_times( array $segment ): array {
        $starts_at_utc = (string) ( $segment['starts_at_utc'] ?? '' );
        $ends_at_utc = (string) ( $segment['ends_at_utc'] ?? '' );
        if ( '' !== $starts_at_utc ) {
            $starts = strtotime( $starts_at_utc );
            $ends = '' !== $ends_at_utc ? strtotime( $ends_at_utc ) : false;
            if ( false === $starts ) {
                return [];
            }

            $lines = [ 'DTSTART:' . gmdate( 'Ymd\THis\Z', $starts ) ];
            if ( false !== $ends && $ends > $starts ) {
                $lines[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', $ends );
            }

            return $lines;
        }

        $date = (string) ( $segment['date'] ?? '' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return [];
        }

        $time = (string) ( $segment['time'] ?? '' );
        $end_date = (string) ( $segment['end_date'] ?? '' );
        $end_time = (string) ( $segment['end_time'] ?? '' );
        if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
            $timezone = $this->normalize_ics_timezone( (string) ( $segment['timezone'] ?? '' ) );
            $start_value = str_replace( [ '-', ':' ], '', $date . 'T' . $time . '00' );
            $lines = [ ( '' !== $timezone ? 'DTSTART;TZID=' . $timezone . ':' : 'DTSTART:' ) . $start_value ];

            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) && preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
                $end_value = str_replace( [ '-', ':' ], '', $end_date . 'T' . $end_time . '00' );
                $lines[] = ( '' !== $timezone ? 'DTEND;TZID=' . $timezone . ':' : 'DTEND:' ) . $end_value;
            } elseif ( preg_match( '/^\d{2}:\d{2}$/', $end_time ) ) {
                $end_value = str_replace( [ '-', ':' ], '', $date . 'T' . $end_time . '00' );
                $lines[] = ( '' !== $timezone ? 'DTEND;TZID=' . $timezone . ':' : 'DTEND:' ) . $end_value;
            }

            return $lines;
        }

        $start_date = date_create_immutable( $date );
        if ( ! $start_date ) {
            return [];
        }

        $end_date_object = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ? date_create_immutable( $end_date ) : null;
        if ( ! $end_date_object || $end_date_object <= $start_date ) {
            $end_date_object = $start_date->modify( '+1 day' );
        } elseif ( 'lodging' !== (string) ( $segment['type'] ?? '' ) ) {
            $end_date_object = $end_date_object->modify( '+1 day' );
        }

        return [
            'DTSTART;VALUE=DATE:' . $start_date->format( 'Ymd' ),
            'DTEND;VALUE=DATE:' . $end_date_object->format( 'Ymd' ),
        ];
    }

    private function normalize_ics_timezone( string $timezone ): string {
        $timezone = trim( $timezone );

        return preg_match( '/^[A-Za-z0-9_+\-\/]+$/', $timezone ) ? $timezone : '';
    }

    private function is_transport_segment( array $segment ): bool {
        $type = (string) ( $segment['type'] ?? '' );
        if ( in_array( $type, [ 'flight', 'train' ], true ) ) {
            return true;
        }

        return 1 === preg_match( '/\bbus(?:ses|es)?\b/i', (string) ( $segment['title'] ?? '' ) . ' ' . (string) ( $segment['details'] ?? '' ) );
    }

    private function escape_ics_text( string $text ): string {
        $text = str_replace( [ "\\", "\r\n", "\r", "\n", ';', ',' ], [ '\\\\', '\n', '\n', '\n', '\;', '\,' ], $text );

        return $text;
    }

    private function fold_ics_line( string $line ): string {
        if ( strlen( $line ) <= 75 ) {
            return $line;
        }

        $characters = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! is_array( $characters ) ) {
            return rtrim( chunk_split( $line, 75, "\r\n " ), "\r\n " );
        }

        $folded_lines = [];
        $current_line = '';
        foreach ( $characters as $character ) {
            if ( strlen( $current_line . $character ) > 75 && '' !== $current_line ) {
                $folded_lines[] = $current_line;
                $current_line = $character;
                continue;
            }

            $current_line .= $character;
        }

        if ( '' !== $current_line ) {
            $folded_lines[] = $current_line;
        }

        return implode( "\r\n ", $folded_lines );
    }

    public function get_quick_plan_draft( string $draft_key ): array {
        if ( '' === $draft_key || ! is_user_logged_in() ) {
            return [];
        }

        $draft = get_transient( $this->get_quick_plan_transient_name( $draft_key ) );
        return is_array( $draft ) ? $draft : [];
    }

    private function get_trip_share_token( int $trip_id, string $mode = 'fellow' ): string {
        if ( ! current_user_can( 'read_travel_app_trip', $trip_id ) ) {
            return '';
        }

        return (string) get_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ), true );
    }

    private function create_trip_share_token( int $trip_id, string $mode = 'fellow' ): string {
        if ( ! current_user_can( 'edit_travel_app_trip', $trip_id ) ) {
            return '';
        }

        $mode = $this->normalize_share_mode( $mode );
        $token = (string) get_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ), true );
        if ( '' !== $token ) {
            return $token;
        }

        $token = wp_generate_password( 32, false, false );
        update_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ), $token );

        return $token;
    }

    private function normalize_share_mode( string $mode ): string {
        return 'public' === $mode ? 'public' : 'fellow';
    }

    private function get_trip_share_token_meta_key( string $mode ): string {
        return 'public' === $this->normalize_share_mode( $mode ) ? '_travel_app_public_share_token' : '_travel_app_share_token';
    }

    private function get_user_calendar_token( int $user_id ): string {
        return (string) get_user_meta( $user_id, '_travel_app_calendar_token', true );
    }

    private function create_user_calendar_token( int $user_id ): string {
        if ( $user_id <= 0 || get_current_user_id() !== $user_id ) {
            return '';
        }

        $token = $this->get_user_calendar_token( $user_id );
        if ( '' !== $token ) {
            return $token;
        }

        $token = wp_generate_password( 32, false, false );
        update_user_meta( $user_id, '_travel_app_calendar_token', $token );

        return $token;
    }

    private function user_calendar_token_matches( int $user_id, string $token ): bool {
        if ( $user_id <= 0 || '' === $token ) {
            return false;
        }

        $stored_token = $this->get_user_calendar_token( $user_id );

        return '' !== $stored_token && hash_equals( $stored_token, $token );
    }

    public function get_trip_summary_parts( array $trip_data, ?string $today = null, bool $include_relative = true ): array {
        $today = $today ?: current_time( 'Y-m-d' );
        $parts = [];

        $date_range = $this->get_trip_date_range_label( $trip_data );
        if ( '' !== $date_range ) {
            $parts[] = $date_range;
        }

        if ( $include_relative ) {
            $relative_label = $this->get_trip_relative_label( $trip_data, $today );
            if ( '' !== $relative_label ) {
                $parts[] = $relative_label;
            }
        }

        $duration_label = $this->get_trip_duration_label( $trip_data );
        if ( '' !== $duration_label ) {
            $parts[] = $duration_label;
        }

        return $parts;
    }

    public function is_trip_active( array $trip_data, ?string $today = null ): bool {
        return Trip::is_active_data( $trip_data, $today );
    }

    public function get_trip_date_range_label( array $trip_data ): string {
        $starts = (string) ( $trip_data['starts_at'] ?? '' );
        $ends = (string) ( $trip_data['ends_at'] ?? '' );

        return $this->format_date_range_label( $starts, $ends );
    }

    public function format_date_label( string $date, bool $include_year = true ): string {
        $timestamp = strtotime( $date . ' 12:00:00' );
        if ( false === $timestamp ) {
            return $date;
        }

        return wp_date( $this->get_date_label_format( $include_year ), $timestamp );
    }

    private function get_date_label_format( bool $include_year = true, bool $include_weekday = true ): string {
        $format = (string) get_option( 'date_format' );
        if ( '' === $format ) {
            $format = 'F j, Y';
        }

        $localized_default_format = _x( 'F j, Y', 'date format' );
        if ( 'F j, Y' === $format && 'F j, Y' !== $localized_default_format ) {
            $format = $localized_default_format;
        }

        if ( ! $include_year ) {
            $format = $this->remove_year_from_date_format( $format );
        }

        if ( $include_weekday && ! $this->date_format_has_unescaped_character( $format, [ 'D', 'l' ] ) ) {
            $format = 'l, ' . $format;
        }

        return $format;
    }

    private function remove_year_from_date_format( string $format ): string {
        $format = preg_replace( '/(^|[\s,.\-\/]+)(?<!\\\\)[YyoxX]([\s,.\-\/]+|$)/', '$1', $format );
        $format = preg_replace( '/([\s,.\-\/]+)(?<!\\\\)[YyoxX]($|[\s,.\-\/]+)/', '$2', (string) $format );

        return trim( (string) $format, " \t\n\r\0\x0B,.-/" );
    }

    private function date_format_has_unescaped_character( string $format, array $characters ): bool {
        $escaped = false;
        foreach ( str_split( $format ) as $character ) {
            if ( $escaped ) {
                $escaped = false;
                continue;
            }

            if ( '\\' === $character ) {
                $escaped = true;
                continue;
            }

            if ( in_array( $character, $characters, true ) ) {
                return true;
            }
        }

        return false;
    }

    public function format_date_range_label( string $starts, string $ends = '' ): string {
        if ( '' !== $starts && $starts === $ends ) {
            return $this->format_date_label( $starts );
        }

        $same_year = '' !== $starts && '' !== $ends && substr( $starts, 0, 4 ) === substr( $ends, 0, 4 );
        $start_label = '' !== $starts ? $this->format_date_label( $starts, ! $same_year ) : '';
        $end_label = '' !== $ends ? $this->format_date_label( $ends ) : '';

        if ( '' !== $start_label && '' !== $end_label && $start_label !== $end_label ) {
            return $start_label . ' - ' . $end_label;
        }

        return $start_label ?: $end_label;
    }

    public function get_segment_duration_label( array $segment ): string {
        $starts = (string) ( $segment['date'] ?? '' );
        $ends = (string) ( $segment['end_date'] ?? '' );

        if ( '' === $starts || '' === $ends ) {
            return '';
        }

        $start_date = date_create_immutable( $starts );
        $end_date = date_create_immutable( $ends );
        if ( ! $start_date || ! $end_date || $end_date <= $start_date ) {
            return '';
        }

        $date_diff = (int) $start_date->diff( $end_date )->format( '%a' );
        if ( 'lodging' === ( $segment['type'] ?? '' ) ) {
            return sprintf( _n( '1 night', '%d nights', $date_diff, 'travel-app' ), $date_diff );
        }

        $days = $date_diff + 1;
        return sprintf( _n( '1 day', '%d days', $days, 'travel-app' ), $days );
    }

    public function get_segment_date_range_label( array $segment, bool $include_duration = true ): string {
        $date_range = $this->format_date_range_label(
            (string) ( $segment['date'] ?? '' ),
            (string) ( $segment['end_date'] ?? '' )
        );

        if ( '' === $date_range || ! $include_duration ) {
            return $date_range;
        }

        $duration_label = $this->get_segment_duration_label( $segment );
        return trim( $date_range . ( $duration_label ? ' · ' . $duration_label : '' ) );
    }

    public function get_segment_date_time_range_label( array $segment, bool $include_duration = true ): string {
        $date_range = $this->get_segment_date_range_label( $segment, $include_duration );
        $time_range = $this->format_time_range_label(
            (string) ( $segment['time'] ?? '' ),
            (string) ( $segment['end_time'] ?? '' )
        );

        return trim( $date_range . ( $time_range ? ' ' . $time_range : '' ) );
    }

    public function format_time_range_label( string $starts, string $ends = '' ): string {
        if ( '' !== $starts && '' !== $ends && $starts !== $ends ) {
            return $starts . ' - ' . $ends;
        }

        return $starts ?: $ends;
    }

    private function get_trip_relative_label( array $trip_data, string $today ): string {
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
    }

    private function get_trip_duration_label( array $trip_data ): string {
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
    }

    public function parse_itinerary_text( string $text ): array {
        $ics_parser = new IcsParser();
        if ( $ics_parser->supports( $text ) ) {
            return $this->normalize_trip_data( $ics_parser->parse( $text ) );
        }

        $parsed = ( new GenericParser() )->parse( $text );
        if ( 'fallback' === (string) ( $parsed['parser'] ?? '' ) && $this->is_quick_plan_text( $text ) ) {
            $segment = $this->parse_quick_plan_text( $text );
            if ( '' !== $segment['date'] ) {
                $parsed = [
                    'title'     => $this->get_quick_plan_trip_title( $segment ),
                    'starts_at' => (string) $segment['date'],
                    'ends_at'   => (string) ( $segment['end_date'] ?: $segment['date'] ),
                    'segments'  => [ $segment ],
                    'parser'    => 'quick-plan',
                    'parser_error' => $parsed['parser_error'] ?? [],
                ];
            }
        }

        return $this->normalize_trip_data( $parsed );
    }

    public function parse_quick_plan_text( string $text ): array {
        return ItineraryItem::normalize( ( new QuickPlanParser() )->parse( $text ) );
    }

    private function is_quick_plan_text( string $text ): bool {
        return ( new QuickPlanParser() )->looks_like_quick_plan( $text );
    }

    private function find_quick_plan_trip_matches( array $segment, string $text ): array {
        $date = (string) ( $segment['date'] ?? '' );
        if ( '' === $date ) {
            return [];
        }

        $matches = [];
        foreach ( array_map( static function( Trip $trip ): array {
            return $trip->to_array();
        }, Trip::for_current_user() ) as $trip_data ) {
            $starts = (string) ( $trip_data['starts_at'] ?? '' );
            $ends = (string) ( $trip_data['ends_at'] ?? '' );
            $date_matches = '' !== $starts && $starts <= $date && ( '' === $ends || $ends >= $date );
            $location_matches = $this->quick_plan_text_matches_trip_location( $text, $segment, $trip_data );

            if ( ! $date_matches ) {
                continue;
            }

            $score = ( $date_matches ? 2 : 0 ) + ( $location_matches ? 1 : 0 );
            $matches[] = [
                'id'               => (int) ( $trip_data['id'] ?? 0 ),
                'title'            => (string) ( $trip_data['title'] ?? '' ),
                'starts_at'        => $starts,
                'ends_at'          => $ends,
                'date_matches'     => $date_matches,
                'location_matches' => $location_matches,
                'score'            => $score,
            ];
        }

        usort( $matches, static function( array $a, array $b ): int {
            return (int) $b['score'] <=> (int) $a['score'];
        } );

        return array_slice( $matches, 0, 5 );
    }

    private function quick_plan_text_matches_trip_location( string $text, array $segment, array $trip_data ): bool {
        $needle_text = $this->normalize_quick_plan_match_text( $text . ' ' . (string) ( $segment['location'] ?? '' ) );
        $trip_text = $this->normalize_quick_plan_match_text( (string) ( $trip_data['title'] ?? '' ) );

        foreach ( (array) ( $trip_data['segments'] ?? [] ) as $trip_segment ) {
            $trip_text .= ' ' . $this->normalize_quick_plan_match_text( (string) ( $trip_segment['location'] ?? '' ) );
            $trip_text .= ' ' . $this->normalize_quick_plan_match_text( (string) ( $trip_segment['end_location'] ?? '' ) );
        }

        foreach ( preg_split( '/\s+/', $needle_text ) as $token ) {
            if ( strlen( $token ) >= 4 && false !== strpos( ' ' . $trip_text . ' ', ' ' . $token . ' ' ) ) {
                return true;
            }
        }

        return false;
    }

    private function normalize_quick_plan_match_text( string $text ): string {
        $text = strtolower( remove_accents( $text ) );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );

        return trim( (string) $text );
    }

    private function store_quick_plan_draft( array $draft ): string {
        $key = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : wp_generate_password( 20, false, false );
        set_transient( $this->get_quick_plan_transient_name( $key ), $draft, 15 * MINUTE_IN_SECONDS );

        return $key;
    }

    private function get_quick_plan_transient_name( string $key ): string {
        return 'travel_app_quick_plan_' . get_current_user_id() . '_' . sanitize_key( $key );
    }

    private function get_quick_plan_trip_title( array $segment ): string {
        $location = trim( (string) ( $segment['location'] ?? '' ) );
        $date = trim( (string) ( $segment['date'] ?? '' ) );

        if ( '' !== $location ) {
            return $location;
        }

        return __( 'Quick Travel Plan', 'travel-app' );
    }

    private function normalize_trip_data( array $data ): array {
        $segments = isset( $data['segments'] ) && is_array( $data['segments'] ) ? $data['segments'] : [];

        return [
            'title'       => sanitize_text_field( (string) ( $data['title'] ?? __( 'Imported Travel Plan', 'travel-app' ) ) ),
            'starts_at'   => sanitize_text_field( (string) ( $data['starts_at'] ?? '' ) ),
            'ends_at'     => sanitize_text_field( (string) ( $data['ends_at'] ?? '' ) ),
            'segments'    => array_values( array_map( [ $this, 'normalize_imported_segment' ], $segments ) ),
            'parser'      => sanitize_key( (string) ( $data['parser'] ?? 'fallback' ) ),
            'parser_error' => Trip::normalize_parser_error( $data['parser_error'] ?? [] ),
        ];
    }

    private function normalize_imported_segment( $segment ): array {
        $normalized = ItineraryItem::normalize( $segment );
        $normalized['details'] = $this->clean_imported_segment_details( $normalized['details'], $normalized );

        return $normalized;
    }

    private function clean_imported_segment_details( string $details, array $segment ): string {
        $details = trim( $details );
        if ( '' === $details ) {
            return '';
        }

        $title = trim( (string) ( $segment['title'] ?? '' ) );
        $date = trim( (string) ( $segment['date'] ?? '' ) );
        $time = trim( (string) ( $segment['time'] ?? '' ) );
        $location = trim( (string) ( $segment['location'] ?? '' ) );
        $overview_parts = array_filter( [ $title, $date, $time, $location ], static function( string $part ): bool {
            return '' !== $part;
        } );

        if ( $this->normalize_detail_comparison_text( $details ) === $this->normalize_detail_comparison_text( implode( ' ', $overview_parts ) ) ) {
            return '';
        }

        $lines = preg_split( '/\R+/', $details );
        $kept = [];
        foreach ( is_array( $lines ) ? $lines : [ $details ] as $line ) {
            $line = trim( (string) $line );
            if ( '' === $line || $this->is_low_value_import_detail_line( $line ) ) {
                continue;
            }

            $kept[] = $line;
        }

        return implode( "\n", $kept );
    }

    private function is_low_value_import_detail_line( string $line ): bool {
        $patterns = [
            '/\b(?:confirmation|confirm(?:ation)? code|booking(?: reference| number| no\.?)?|reservation(?: number| no\.?)?|reference(?: number| no\.?)?|order(?: number| no\.?)?|ticket(?: number| no\.?)?|e-?ticket|pnr|pin|voucher|invoice|receipt|loyalty|member number)\b/i',
            '/\b(?:payment|paid|total|subtotal|tax|fee|refund|cancell?ation policy|terms and conditions|privacy policy)\b/i',
            '/\b(?:do not reply|unsubscribe|manage booking|view booking|view reservation|download app|add to calendar)\b/i',
            '/^\s*(?:[A-Z0-9]{5,}[-\s]?){1,4}\s*$/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $line ) ) {
                return true;
            }
        }

        return false;
    }

    private function normalize_detail_comparison_text( string $text ): string {
        $text = strtolower( $text );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );

        return trim( (string) $text );
    }

    private function create_trip_item( int $trip_id, array $segment ) {
        $segment = ItineraryItem::normalize( $segment );

        $item_id = wp_insert_post( [
            'post_type'    => 'travel_app_item',
            'post_status'  => 'private',
            'post_author'  => get_current_user_id(),
            'post_title'   => $segment['title'] ?: __( 'Untitled item', 'travel-app' ),
            'post_content' => $segment['details'],
        ], true );

        if ( is_wp_error( $item_id ) ) {
            return $item_id;
        }

        $term_result = wp_set_object_terms( $item_id, [ $trip_id ], 'travel_app_trip', false );
        if ( is_wp_error( $term_result ) ) {
            wp_trash_post( $item_id );
            return $term_result;
        }

        $this->update_item_meta( (int) $item_id, $segment );
        update_post_meta( (int) $item_id, '_travel_app_owner_user_id', Trip::get_owner_id( $trip_id ) );
        update_post_meta( (int) $item_id, '_travel_app_created_by_user_id', get_current_user_id() );

        return (int) $item_id;
    }

    private function update_item_meta( int $item_id, array $segment ): void {
        $previous_url = (string) get_post_meta( $item_id, '_travel_app_url', true );

        update_post_meta( $item_id, '_travel_app_type', $segment['type'] );
        update_post_meta( $item_id, '_travel_app_date', $segment['date'] );
        update_post_meta( $item_id, '_travel_app_end_date', $segment['end_date'] );
        update_post_meta( $item_id, '_travel_app_time', $segment['time'] );
        update_post_meta( $item_id, '_travel_app_end_time', $segment['end_time'] );
        update_post_meta( $item_id, '_travel_app_starts_at_utc', $segment['starts_at_utc'] );
        update_post_meta( $item_id, '_travel_app_ends_at_utc', $segment['ends_at_utc'] );
        update_post_meta( $item_id, '_travel_app_timezone', $segment['timezone'] );
        update_post_meta( $item_id, '_travel_app_location', $segment['location'] );
        update_post_meta( $item_id, '_travel_app_end_location', $segment['end_location'] );
        update_post_meta( $item_id, '_travel_app_url', $segment['url'] );
        update_post_meta( $item_id, '_travel_app_sort', $segment['starts_at_utc'] ?: trim( $segment['date'] . ' ' . $segment['time'] ) );

        $this->get_url_preview_service()->sync_item_preview( $item_id, $segment, $previous_url );
    }

    private function save_trip( array $parsed, string $source_text, ?int $owner_user_id = null ) {
        $owner_user_id = $owner_user_id ?: get_current_user_id();
        $actor_user_id = get_current_user_id();
        $title = $parsed['title'] ?: __( 'Imported Travel Plan', 'travel-app' );

        $trip = wp_insert_term( $title, 'travel_app_trip', [
            'slug' => sanitize_title( $title . '-' . $owner_user_id . '-' . time() ),
        ] );

        if ( is_wp_error( $trip ) ) {
            return $trip;
        }

        $trip_id = (int) $trip['term_id'];
        update_term_meta( $trip_id, '_travel_app_user_id', $owner_user_id );
        update_term_meta( $trip_id, '_travel_app_created_by_user_id', $actor_user_id );
        update_term_meta( $trip_id, '_travel_app_starts_at', $parsed['starts_at'] );
        update_term_meta( $trip_id, '_travel_app_ends_at', $parsed['ends_at'] );
        update_term_meta( $trip_id, '_travel_app_parser', $parsed['parser'] );
        update_term_meta( $trip_id, '_travel_app_parser_error', $parsed['parser_error'] ?? [] );
        update_term_meta( $trip_id, '_travel_app_source_text', $source_text );

        if ( $owner_user_id !== $actor_user_id ) {
            add_term_meta( $trip_id, '_travel_app_editor_user_ids', $actor_user_id, false );
        }

        $created_items = [];
        foreach ( $parsed['segments'] as $segment ) {
            $item_id = $this->create_trip_item( $trip_id, $segment );
            if ( is_wp_error( $item_id ) ) {
                foreach ( $created_items as $created_item_id ) {
                    wp_trash_post( $created_item_id );
                }
                wp_delete_term( $trip_id, 'travel_app_trip' );
                return $item_id;
            }
            $created_items[] = $item_id;
        }

        if ( ! empty( $created_items ) ) {
            $this->update_trip_bounds_from_items( $trip_id );
        }

        return $trip_id;
    }

    public function activate(): void {
        $this->register_post_types();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }
}
