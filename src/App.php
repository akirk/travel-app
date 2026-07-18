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
            // 'my_apps_icon' => null,
        ] );

        add_action( 'init', [ $this, 'register_post_types' ] );
        add_action( 'init', [ $this, 'register_taxonomies' ] );
        add_action( 'admin_post_travel_app_import', [ $this, 'handle_import' ] );
        add_action( 'admin_post_travel_app_update_trip', [ $this, 'handle_update_trip' ] );
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
        add_action( 'wp_app_head', [ $this, 'enqueue_assets' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_shared_timeline' ], 0 );
    }

    protected function get_url_path(): string {
        return 'travel-app';
    }

    protected function get_template_dir(): string {
        return dirname( __DIR__ ) . '/templates';
    }

    public function enqueue_assets(): void {
        wp_enqueue_script(
            'travel-app-timeline-time',
            plugins_url( 'assets/js/timeline-time.js', dirname( __DIR__ ) . '/travel-app.php' ),
            [],
            '1.0.0',
            true
        );
    }

    public function is_demo_mode_enabled(): bool {
        $enabled = defined( 'TRAVEL_APP_DEMO_MODE' ) && TRAVEL_APP_DEMO_MODE;

        return (bool) apply_filters( 'travel_app_demo_mode_enabled', $enabled );
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
        $this->app->route( 'trip/{id}/item/{item_id}', 'item.php' );
    }

    protected function setup_menu(): void {
        /*
         * Add WpApp masterbar/menu entries here. BaseApp calls this method
         * during init(), after routes have been registered.
         *
         * $this->app->add_menu_item( 'overview', 'Overview', home_url( '/travel-app/overview' ) );
         */
    }

    public function register_post_types(): void {
        register_post_type( 'travel_app_item', [
            'labels'       => [
                'name'          => __( 'Itinerary Items', 'travel-app' ),
                'singular_name' => __( 'Itinerary Item', 'travel-app' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'supports'     => [ 'title', 'editor', 'author' ],
            'map_meta_cap' => true,
        ] );
    }

    public function register_taxonomies(): void {
        register_taxonomy( 'travel_app_trip', 'travel_app_item', [
            'labels'            => [
                'name'          => __( 'Travel Plans', 'travel-app' ),
                'singular_name' => __( 'Travel Plan', 'travel-app' ),
            ],
            'public'            => false,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
        ] );
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
                'properties'           => [],
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
                    'id'          => [ 'type' => 'integer' ],
                    'title'       => [ 'type' => 'string' ],
                    'starts_at'   => [ 'type' => 'string' ],
                    'ends_at'     => [ 'type' => 'string' ],
                    'segments'    => [ 'type' => 'array' ],
                    'parser'      => [ 'type' => 'string' ],
                    'url'         => [ 'type' => 'string' ],
                ],
            ],
            'execute_callback'    => [ $this, 'import_ability_itinerary' ],
            'permission_callback' => function() {
                return current_user_can( 'read' );
            },
            'meta'                => [
                'annotations' => [
                    'instructions' => 'After importing, summarize the created travel plan and include the app URL for review. Ask for missing dates or locations only if the saved data is clearly incomplete.',
                    'readonly'     => false,
                    'destructive'  => false,
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
        return [
            'trips' => array_map( [ $this, 'format_trip_for_output' ], $this->get_user_trips() ),
        ];
    }

    public function register_ai_assistant_ability_domains( array $domains ): array {
        $domains['travel-app'] = 'Travel App, itinerary, travel plans, flights, lodging, booking confirmations, reservations, travel organizer';
        return $domains;
    }

    public function get_ai_assistant_ability_instructions( string $instructions, string $ability_id, $args, $result ): string {
        if ( 'travel-app/import-itinerary' === $ability_id && ! empty( $result['id'] ) ) {
            $instructions = 'Tell the user the travel plan was saved. Summarize title, dates, and travel segments, then link to the Travel App URL if present.';
        }

        return $instructions;
    }

    public function register_ai_assistant_welcome_tips( array $tips, array $context ): array {
        $tips['travel-app'] = [
            __( 'Paste a booking confirmation and ask me to add it to Travel App.', 'travel-app' ),
            __( 'Ask me to summarize your saved travel plans or find the next reservation.', 'travel-app' ),
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

        $output = $this->format_trip_for_output( get_term( $trip_id, 'travel_app_trip' ) );
        $output['url'] = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );

        return $output;
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

        $redirect = home_url( '/' . $this->get_url_path() . '/' );
        $draft_key = isset( $_POST['quick_plan_draft'] ) ? sanitize_key( wp_unslash( $_POST['quick_plan_draft'] ) ) : '';
        if ( '' !== $draft_key ) {
            $target = isset( $_POST['quick_plan_target'] ) ? sanitize_text_field( wp_unslash( $_POST['quick_plan_target'] ) ) : '';
            if ( isset( $_POST['quick_plan_update_draft'] ) ) {
                $this->update_quick_plan_draft_submission( $draft_key, $redirect );
            }
            $this->save_quick_plan_draft_submission( $draft_key, $target, $redirect );
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
                    $trip_id = $this->save_trip( $parsed, $text );

                    if ( is_wp_error( $trip_id ) ) {
                        wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $trip_id->get_error_code() ), $redirect ) );
                        exit;
                    }

                    wp_safe_redirect( add_query_arg( 'imported', rawurlencode( (string) $trip_id ), $redirect ) );
                    exit;
                }
            }
        }
        $trip_id = $this->save_trip( $parsed, $text );

        if ( is_wp_error( $trip_id ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', rawurlencode( $trip_id->get_error_code() ), $redirect ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'imported', rawurlencode( (string) $trip_id ), $redirect ) );
        exit;
    }

    private function update_quick_plan_draft_submission( string $draft_key, string $redirect ): void {
        $draft = $this->get_quick_plan_draft( $draft_key );
        if ( empty( $draft ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
            exit;
        }

        $segment = $this->segment_from_request();
        if ( empty( $segment ) || empty( $segment['date'] ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
            exit;
        }

        $source_text = (string) ( $draft['text'] ?? '' );
        $trip_title = isset( $_POST['quick_plan_trip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quick_plan_trip_title'] ) ) : '';
        $draft['segment'] = $segment;
        $draft['matches'] = $this->find_quick_plan_trip_matches( $segment, $source_text . ' ' . $segment['title'] . ' ' . $segment['location'] );
        $draft['trip_title'] = '' !== trim( $trip_title ) ? $trip_title : $this->get_quick_plan_trip_title( $segment );
        set_transient( $this->get_quick_plan_transient_name( $draft_key ), $draft, 15 * MINUTE_IN_SECONDS );

        wp_safe_redirect( add_query_arg( 'quick_plan_draft', rawurlencode( $draft_key ), $redirect ) );
        exit;
    }

    private function save_quick_plan_draft_submission( string $draft_key, string $target, string $redirect ): void {
        $draft = $this->get_quick_plan_draft( $draft_key );

        $segment = $this->segment_from_request();
        if ( empty( $segment ) || empty( $segment['date'] ) ) {
            wp_safe_redirect( add_query_arg( 'travel_app_error', 'quick_plan_invalid', $redirect ) );
            exit;
        }

        if ( 'new' === $target || '' === $target ) {
            $trip_title = isset( $_POST['quick_plan_trip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['quick_plan_trip_title'] ) ) : '';
            $trip_id = $this->save_trip( [
                'title'     => '' !== trim( $trip_title ) ? $trip_title : $this->get_quick_plan_trip_title( $segment ),
                'starts_at' => (string) $segment['date'],
                'ends_at'   => (string) ( $segment['end_date'] ?: $segment['date'] ),
                'segments'  => [ $segment ],
                'parser'    => sanitize_key( (string) ( $draft['parser'] ?? 'quick-plan' ) ),
            ], (string) ( $draft['text'] ?? '' ) );
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

    public function handle_update_trip(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_die( esc_html__( 'You must be logged in to edit travel plans.', 'travel-app' ), 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_admin_referer( 'travel_app_update_trip_' . $trip_id );

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/' );
        $title = isset( $_POST['trip_title'] ) ? sanitize_text_field( wp_unslash( $_POST['trip_title'] ) ) : '';
        $updated = $this->update_user_trip_title( $trip_id, $title );

        if ( is_wp_error( $updated ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $updated->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'trip_updated', rawurlencode( (string) $trip_id ), $redirect );
        }

        wp_safe_redirect( $redirect );
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
            'mode'    => $this->normalize_share_mode( $mode ),
            'url'     => $this->get_trip_share_url( $trip_id, $mode ),
            'message' => __( 'Read-only timeline share link generated.', 'travel-app' ),
        ] );
    }

    public function handle_remove_share_link(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in to update travel plan sharing.', 'travel-app' ) ], 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        $mode = isset( $_POST['share_mode'] ) ? sanitize_key( wp_unslash( $_POST['share_mode'] ) ) : 'fellow';
        check_ajax_referer( 'travel_app_share_link_' . $trip_id, 'nonce' );

        if ( ! $this->get_user_trip( $trip_id ) ) {
            wp_send_json_error( [ 'message' => __( 'This travel plan cannot be updated.', 'travel-app' ) ], 404 );
        }

        $this->clear_trip_public_cache( $trip_id );
        delete_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ) );

        wp_send_json_success( [
            'mode'    => $this->normalize_share_mode( $mode ),
            'url'     => '',
            'message' => __( 'Read-only timeline share link removed.', 'travel-app' ),
        ] );
    }

    public function handle_clear_share_cache(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => __( 'You must be logged in to refresh shared travel plans.', 'travel-app' ) ], 403 );
        }

        $trip_id = isset( $_POST['trip_id'] ) ? absint( $_POST['trip_id'] ) : 0;
        check_ajax_referer( 'travel_app_share_link_' . $trip_id, 'nonce' );

        if ( ! $this->get_user_trip( $trip_id ) ) {
            wp_send_json_error( [ 'message' => __( 'This travel plan cannot be refreshed.', 'travel-app' ) ], 404 );
        }

        $this->clear_trip_public_cache( $trip_id );

        wp_send_json_success( [
            'urls'    => [
                'fellow' => $this->get_trip_share_url( $trip_id, 'fellow' ),
                'public' => $this->get_trip_share_url( $trip_id, 'public' ),
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

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/item/' . $index . '/' );
        $segment = [
            'type'     => isset( $_POST['segment_type'] ) ? sanitize_key( wp_unslash( $_POST['segment_type'] ) ) : 'other',
            'title'    => isset( $_POST['segment_title'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_title'] ) ) : '',
            'date'     => isset( $_POST['segment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_date'] ) ) : '',
            'end_date' => isset( $_POST['segment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_date'] ) ) : '',
            'time'     => isset( $_POST['segment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_time'] ) ) : '',
            'end_time' => isset( $_POST['segment_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_time'] ) ) : '',
            'location' => isset( $_POST['segment_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_location'] ) ) : '',
            'end_location' => isset( $_POST['segment_end_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_location'] ) ) : '',
            'url'      => isset( $_POST['segment_url'] ) ? esc_url_raw( wp_unslash( $_POST['segment_url'] ) ) : '',
            'url_preview' => $this->url_preview_from_request(),
            'details'  => isset( $_POST['segment_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['segment_details'] ) ) : '',
        ];

        $updated = $this->update_user_trip_segment( $trip_id, $index, $segment );
        if ( is_wp_error( $updated ) ) {
            $redirect = add_query_arg( 'travel_app_error', rawurlencode( $updated->get_error_code() ), $redirect );
        } else {
            $redirect = add_query_arg( 'updated', rawurlencode( (string) $index ), $redirect );
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

        $segment = $this->segment_from_request();
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

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/item/' . $index . '/' );
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

        $redirect = home_url( '/' . $this->get_url_path() . '/trip/' . $trip_id . '/item/' . $index . '/' );
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
        $term = $this->get_user_trip( $trip_id );

        if ( ! $term ) {
            return new \WP_Error( 'delete_forbidden', __( 'This travel plan cannot be deleted.', 'travel-app' ) );
        }

        $this->clear_trip_public_cache( $trip_id );

        foreach ( $this->get_trip_item_posts( $trip_id ) as $item ) {
            wp_trash_post( $item->ID );
        }

        $deleted = wp_delete_term( $trip_id, 'travel_app_trip' );
        if ( ! $deleted || is_wp_error( $deleted ) ) {
            return new \WP_Error( 'delete_failed', __( 'The travel plan could not be deleted.', 'travel-app' ) );
        }

        return true;
    }

    private function update_user_trip_title( int $trip_id, string $title ) {
        if ( '' === trim( $title ) ) {
            return new \WP_Error( 'empty_title', __( 'Travel plan title cannot be empty.', 'travel-app' ) );
        }

        if ( ! $this->get_user_trip( $trip_id ) ) {
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
        if ( ! $this->get_user_trip( $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item = $this->get_user_trip_item_post( $trip_id, $index );
        if ( ! $item ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        $segment = $this->normalize_segment( $segment );
        $updated = wp_update_post( [
            'ID'           => $item->ID,
            'post_title'   => $segment['title'] ?: __( 'Untitled item', 'travel-app' ),
            'post_content' => $segment['details'],
        ], true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $this->update_item_meta( $item->ID, $segment );
        $this->update_trip_bounds_from_items( $trip_id );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function add_user_trip_segment( int $trip_id, array $segment ) {
        if ( ! $this->get_user_trip( $trip_id ) ) {
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
        if ( ! $this->get_user_trip( $trip_id ) ) {
            return new \WP_Error( 'edit_forbidden', __( 'This travel plan cannot be edited.', 'travel-app' ) );
        }

        $item = $this->get_user_trip_item_post( $trip_id, $index );
        if ( ! $item ) {
            return new \WP_Error( 'segment_not_found', __( 'This itinerary item could not be found.', 'travel-app' ) );
        }

        $deleted = wp_trash_post( $item->ID );
        if ( ! $deleted ) {
            return new \WP_Error( 'segment_delete_failed', __( 'This itinerary item could not be deleted.', 'travel-app' ) );
        }

        $this->update_trip_bounds_from_items( $trip_id );
        $this->clear_trip_public_cache( $trip_id );

        return true;
    }

    private function upload_user_trip_item_attachments( int $trip_id, int $index ) {
        $item = $this->get_user_trip_item_post( $trip_id, $index );
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
            $attachment_id = media_handle_upload( 'item_attachment', $item->ID );

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
        $attachment = $this->get_user_trip_item_attachment( $trip_id, $index, $attachment_id );
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

    private function segment_from_request(): array {
        return [
            'type'     => isset( $_POST['segment_type'] ) ? sanitize_key( wp_unslash( $_POST['segment_type'] ) ) : 'other',
            'title'    => isset( $_POST['segment_title'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_title'] ) ) : '',
            'date'     => isset( $_POST['segment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_date'] ) ) : '',
            'end_date' => isset( $_POST['segment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_date'] ) ) : '',
            'time'     => isset( $_POST['segment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_time'] ) ) : '',
            'end_time' => isset( $_POST['segment_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_time'] ) ) : '',
            'location' => isset( $_POST['segment_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_location'] ) ) : '',
            'end_location' => isset( $_POST['segment_end_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_location'] ) ) : '',
            'url'      => isset( $_POST['segment_url'] ) ? esc_url_raw( wp_unslash( $_POST['segment_url'] ) ) : '',
            'url_preview' => $this->url_preview_from_request(),
            'details'  => isset( $_POST['segment_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['segment_details'] ) ) : '',
        ];
    }

    private function url_preview_from_request(): array {
        return [
            'title'       => isset( $_POST['segment_url_preview_title'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_url_preview_title'] ) ) : '',
            'description' => isset( $_POST['segment_url_preview_description'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_url_preview_description'] ) ) : '',
            'image'       => isset( $_POST['segment_url_preview_image'] ) ? esc_url_raw( wp_unslash( $_POST['segment_url_preview_image'] ) ) : '',
        ];
    }

    private function update_trip_bounds_from_items( int $trip_id ): void {
        $dates = [];
        foreach ( $this->get_trip_segments( $trip_id ) as $segment ) {
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

    public function get_user_trips(): array {
        if ( ! is_user_logged_in() ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => 'travel_app_trip',
            'hide_empty' => false,
            'number'     => 50,
            'meta_query' => [
                [
                    'key'   => '_travel_app_user_id',
                    'value' => (string) get_current_user_id(),
                ],
            ],
        ] );

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }

        usort( $terms, static function( \WP_Term $a, \WP_Term $b ): int {
            $a_start = (string) get_term_meta( $a->term_id, '_travel_app_starts_at', true );
            $b_start = (string) get_term_meta( $b->term_id, '_travel_app_starts_at', true );
            return strcmp( $b_start, $a_start );
        } );

        return $terms;
    }

    public function get_user_trip( int $trip_id ) {
        if ( ! is_user_logged_in() || $trip_id <= 0 ) {
            return null;
        }

        $term = get_term( $trip_id, 'travel_app_trip' );
        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        $user_id = (int) get_term_meta( $trip_id, '_travel_app_user_id', true );
        if ( $user_id !== get_current_user_id() ) {
            return null;
        }

        return $term;
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

    public function get_public_trip_by_share_token( int $trip_id, string $token ) {
        if ( $trip_id <= 0 || '' === $token ) {
            return null;
        }

        if ( '' === $this->get_trip_share_mode_by_token( $trip_id, $token ) ) {
            return null;
        }

        $term = get_term( $trip_id, 'travel_app_trip' );
        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return $term;
    }

    public function get_trip_share_mode_by_token( int $trip_id, string $token ): string {
        if ( $trip_id <= 0 || '' === $token ) {
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

    public function get_trip_owner_id( int $trip_id ): int {
        return (int) get_term_meta( $trip_id, '_travel_app_user_id', true );
    }

    public function get_user_trip_segment( int $trip_id, int $index ) {
        $item = $this->get_user_trip_item_post( $trip_id, $index );

        return $item ? $this->format_segment_for_output( $item ) : null;
    }

    public function get_quick_plan_draft( string $draft_key ): array {
        if ( '' === $draft_key || ! is_user_logged_in() ) {
            return [];
        }

        $draft = get_transient( $this->get_quick_plan_transient_name( $draft_key ) );
        return is_array( $draft ) ? $draft : [];
    }

    public function format_trip_for_output( $term, ?int $user_id = null ): array {
        if ( is_numeric( $term ) ) {
            $term = get_term( (int) $term, 'travel_app_trip' );
        }

        if ( ! $term || is_wp_error( $term ) ) {
            return [];
        }

        $segments = $this->get_trip_segments( (int) $term->term_id, $user_id );

        return [
            'id'            => (int) $term->term_id,
            'title'         => (string) $term->name,
            'starts_at'     => (string) get_term_meta( $term->term_id, '_travel_app_starts_at', true ),
            'ends_at'       => (string) get_term_meta( $term->term_id, '_travel_app_ends_at', true ),
            'segments'      => $segments,
            'segment_count' => count( $segments ),
            'parser'        => (string) get_term_meta( $term->term_id, '_travel_app_parser', true ),
        ];
    }

    private function get_trip_share_token( int $trip_id, string $mode = 'fellow' ): string {
        if ( ! $this->get_user_trip( $trip_id ) ) {
            return '';
        }

        return (string) get_term_meta( $trip_id, $this->get_trip_share_token_meta_key( $mode ), true );
    }

    private function create_trip_share_token( int $trip_id, string $mode = 'fellow' ): string {
        if ( ! $this->get_user_trip( $trip_id ) ) {
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

    public function get_trip_summary_parts( array $trip_data, ?string $today = null ): array {
        $today = $today ?: current_time( 'Y-m-d' );
        $parts = [];

        $date_range = $this->get_trip_date_range_label( $trip_data );
        if ( '' !== $date_range ) {
            $parts[] = $date_range;
        }

        $relative_label = $this->get_trip_relative_label( $trip_data, $today );
        if ( '' !== $relative_label ) {
            $parts[] = $relative_label;
        }

        $duration_label = $this->get_trip_duration_label( $trip_data );
        if ( '' !== $duration_label ) {
            $parts[] = $duration_label;
        }

        return $parts;
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

    private function get_user_trip_item_post( int $trip_id, int $item_id ) {
        if ( ! $this->get_user_trip( $trip_id ) || $item_id <= 0 ) {
            return null;
        }

        $post = get_post( $item_id );
        if ( ! $post || 'travel_app_item' !== $post->post_type || (int) $post->post_author !== get_current_user_id() ) {
            return null;
        }

        if ( 'trash' === $post->post_status || ! has_term( $trip_id, 'travel_app_trip', $post ) ) {
            return null;
        }

        return $post;
    }

    private function get_user_trip_item_attachment( int $trip_id, int $item_id, int $attachment_id ) {
        $item = $this->get_user_trip_item_post( $trip_id, $item_id );
        if ( ! $item || $attachment_id <= 0 ) {
            return null;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            return null;
        }

        if ( (int) $attachment->post_parent !== (int) $item->ID || (int) $attachment->post_author !== get_current_user_id() ) {
            return null;
        }

        return $attachment;
    }

    private function get_trip_item_posts( int $trip_id, ?int $user_id = null ): array {
        $user_id = $user_id ?? get_current_user_id();

        return get_posts( [
            'post_type'      => 'travel_app_item',
            'post_status'    => [ 'private', 'publish', 'draft' ],
            'author'         => $user_id,
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_travel_app_sort',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'travel_app_trip',
                    'field'    => 'term_id',
                    'terms'    => [ $trip_id ],
                ],
            ],
        ] );
    }

    private function get_trip_segments( int $trip_id, ?int $user_id = null ): array {
        return array_map( [ $this, 'format_segment_for_output' ], $this->get_trip_item_posts( $trip_id, $user_id ) );
    }

    private function format_segment_for_output( \WP_Post $post ): array {
        return [
            'id'       => (int) $post->ID,
            'type'     => (string) get_post_meta( $post->ID, '_travel_app_type', true ),
            'title'    => (string) $post->post_title,
            'date'     => (string) get_post_meta( $post->ID, '_travel_app_date', true ),
            'end_date' => (string) get_post_meta( $post->ID, '_travel_app_end_date', true ),
            'time'     => (string) get_post_meta( $post->ID, '_travel_app_time', true ),
            'end_time' => (string) get_post_meta( $post->ID, '_travel_app_end_time', true ),
            'starts_at_utc' => (string) get_post_meta( $post->ID, '_travel_app_starts_at_utc', true ),
            'ends_at_utc' => (string) get_post_meta( $post->ID, '_travel_app_ends_at_utc', true ),
            'timezone' => (string) get_post_meta( $post->ID, '_travel_app_timezone', true ),
            'location' => (string) get_post_meta( $post->ID, '_travel_app_location', true ),
            'end_location' => (string) get_post_meta( $post->ID, '_travel_app_end_location', true ),
            'url'      => (string) get_post_meta( $post->ID, '_travel_app_url', true ),
            'url_preview' => $this->get_url_preview_service()->get_item_preview( $post->ID ),
            'url_preview_debug' => $this->get_url_preview_service()->get_item_preview_debug( $post->ID ),
            'attachments' => $this->get_item_attachments_for_output( $post->ID ),
            'details'  => (string) $post->post_content,
        ];
    }

    private function get_item_attachments_for_output( int $item_id ): array {
        $attachments = get_children( [
            'post_parent'    => $item_id,
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( empty( $attachments ) || ! is_array( $attachments ) ) {
            return [];
        }

        return array_values( array_map( static function( \WP_Post $attachment ): array {
            $file = get_attached_file( $attachment->ID );
            $size = $file && file_exists( $file ) ? size_format( filesize( $file ) ) : '';

            return [
                'id'       => (int) $attachment->ID,
                'title'    => (string) get_the_title( $attachment ),
                'filename' => wp_basename( (string) get_attached_file( $attachment->ID ) ),
                'mime'     => (string) get_post_mime_type( $attachment ),
                'size'     => $size,
                'url'      => (string) wp_get_attachment_url( $attachment->ID ),
            ];
        }, $attachments ) );
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
        return $this->normalize_segment( ( new QuickPlanParser() )->parse( $text ) );
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
        foreach ( array_map( [ $this, 'format_trip_for_output' ], $this->get_user_trips() ) as $trip_data ) {
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
            'segments'    => array_values( array_map( [ $this, 'normalize_segment' ], $segments ) ),
            'parser'      => sanitize_key( (string) ( $data['parser'] ?? 'fallback' ) ),
            'parser_error' => $this->normalize_parser_error( $data['parser_error'] ?? [] ),
        ];
    }

    private function normalize_parser_error( $error ): array {
        if ( ! is_array( $error ) ) {
            return [];
        }

        $code = sanitize_key( (string) ( $error['code'] ?? '' ) );
        $message = sanitize_text_field( (string) ( $error['message'] ?? '' ) );
        if ( '' === $code && '' === $message ) {
            return [];
        }

        return [
            'code'    => $code,
            'message' => $message,
        ];
    }

    private function normalize_segment( $segment ): array {
        $segment = is_array( $segment ) ? $segment : [];
        $type = sanitize_key( (string) ( $segment['type'] ?? 'other' ) );
        if ( 'hotel' === $type ) {
            $type = 'lodging';
        }
        $allowed_types = [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ];

        return [
            'type'     => in_array( $type, $allowed_types, true ) ? $type : 'other',
            'title'    => sanitize_text_field( (string) ( $segment['title'] ?? '' ) ),
            'date'     => sanitize_text_field( (string) ( $segment['date'] ?? '' ) ),
            'end_date' => sanitize_text_field( (string) ( $segment['end_date'] ?? '' ) ),
            'time'     => sanitize_text_field( (string) ( $segment['time'] ?? '' ) ),
            'end_time' => sanitize_text_field( (string) ( $segment['end_time'] ?? '' ) ),
            'starts_at_utc' => sanitize_text_field( (string) ( $segment['starts_at_utc'] ?? '' ) ),
            'ends_at_utc' => sanitize_text_field( (string) ( $segment['ends_at_utc'] ?? '' ) ),
            'timezone' => sanitize_text_field( (string) ( $segment['timezone'] ?? '' ) ),
            'location' => sanitize_text_field( (string) ( $segment['location'] ?? '' ) ),
            'end_location' => sanitize_text_field( (string) ( $segment['end_location'] ?? '' ) ),
            'url'      => esc_url_raw( (string) ( $segment['url'] ?? '' ) ),
            'url_preview' => $this->get_url_preview_service()->normalize_preview( $segment['url_preview'] ?? [] ),
            'details'  => sanitize_textarea_field( (string) ( $segment['details'] ?? '' ) ),
        ];
    }

    private function create_trip_item( int $trip_id, array $segment ) {
        $segment = $this->normalize_segment( $segment );

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

    private function save_trip( array $parsed, string $source_text ) {
        $title = $parsed['title'] ?: __( 'Imported Travel Plan', 'travel-app' );

        $trip = wp_insert_term( $title, 'travel_app_trip', [
            'slug' => sanitize_title( $title . '-' . get_current_user_id() . '-' . time() ),
        ] );

        if ( is_wp_error( $trip ) ) {
            return $trip;
        }

        $trip_id = (int) $trip['term_id'];
        update_term_meta( $trip_id, '_travel_app_user_id', get_current_user_id() );
        update_term_meta( $trip_id, '_travel_app_starts_at', $parsed['starts_at'] );
        update_term_meta( $trip_id, '_travel_app_ends_at', $parsed['ends_at'] );
        update_term_meta( $trip_id, '_travel_app_parser', $parsed['parser'] );
        update_term_meta( $trip_id, '_travel_app_source_text', $source_text );

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

        $this->update_trip_bounds_from_items( $trip_id );

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
