<?php

namespace TravelApp;

class ItineraryItem {
    public const TYPES = [ 'flight', 'lodging', 'train', 'car', 'activity', 'other' ];

    public int $id;
    public string $type;
    public string $title;
    public string $date;
    public string $end_date;
    public string $time;
    public string $end_time;
    public string $starts_at_utc;
    public string $ends_at_utc;
    public string $timezone;
    public string $location;
    public string $end_location;
    public string $url;
    public string $app_url;
    public array $url_preview;
    public array $url_preview_debug;
    public array $attachments;
    public string $details;
    private \WP_Post $post;
    private int $trip_id;

    public function __construct( \WP_Post $post, ?int $trip_id = null ) {
        $this->post = $post;
        $this->id = (int) $post->ID;
        $this->trip_id = $trip_id ?? self::resolve_trip_id( $post->ID );
        $preview_service = new UrlPreviewService();

        $this->type = (string) get_post_meta( $this->id, '_travel_app_type', true );
        $this->title = (string) $this->post->post_title;
        $this->date = (string) get_post_meta( $this->id, '_travel_app_date', true );
        $this->end_date = (string) get_post_meta( $this->id, '_travel_app_end_date', true );
        $this->time = (string) get_post_meta( $this->id, '_travel_app_time', true );
        $this->end_time = (string) get_post_meta( $this->id, '_travel_app_end_time', true );
        $this->starts_at_utc = (string) get_post_meta( $this->id, '_travel_app_starts_at_utc', true );
        $this->ends_at_utc = (string) get_post_meta( $this->id, '_travel_app_ends_at_utc', true );
        $this->timezone = (string) get_post_meta( $this->id, '_travel_app_timezone', true );
        $this->location = (string) get_post_meta( $this->id, '_travel_app_location', true );
        $this->end_location = (string) get_post_meta( $this->id, '_travel_app_end_location', true );
        $this->url = (string) get_post_meta( $this->id, '_travel_app_url', true );
        $this->app_url = $this->trip_id ? home_url( '/travel-app/trip/' . $this->trip_id . '/#segment-' . $this->id ) : '';
        $this->url_preview = $preview_service->get_item_preview( $this->id );
        $this->url_preview_debug = $preview_service->get_item_preview_debug( $this->id );
        $this->attachments = $this->attachments();
        $this->details = (string) $this->post->post_content;
    }

    public static function schema(): array {
        return [
            'type'       => 'object',
            'properties' => self::schema_properties(),
        ];
    }

    public static function array_schema(): array {
        return [
            'type'  => 'array',
            'items' => self::schema(),
        ];
    }

    public static function input_schema(): array {
        return [
            'type'                 => 'object',
            'properties'           => self::input_schema_properties(),
            'additionalProperties' => false,
        ];
    }

    private static function field_definitions(): array {
        return [
            'id'                => [
                'schema' => [ 'type' => 'integer', 'description' => 'Itinerary item ID. Use with traveler/get-itinerary-item, traveler/update-itinerary-item, or traveler/delete-itinerary-item.' ],
            ],
            'type'              => [
                'schema' => [ 'type' => 'string', 'enum' => self::TYPES, 'description' => 'Kind of itinerary item: flight for air travel, lodging for a hotel or overnight stay, train for rail travel, car for a rental car, activity for an event or visit, and other when none fits. Defaults to other when omitted.' ],
            ],
            'title'             => [
                'schema' => [ 'type' => 'string', 'description' => 'Short user-visible title, such as LH 123 Berlin to Frankfurt, Hotel Riverside, or Museum visit. Include a flight or train number when supplied. Do not invent booking details.' ],
            ],
            'date'              => [
                'schema' => [ 'type' => 'string', 'description' => 'Local start date as YYYY-MM-DD, for example 2026-10-03. Departure date for transport, check-in date for lodging, pickup date for a rental car, or event date for an activity. Omit when unknown.' ],
            ],
            'end_date'          => [
                'schema' => [ 'type' => 'string', 'description' => 'Local end date as YYYY-MM-DD: arrival date for transport, checkout date for lodging, or return date for a rental car. Set explicitly for overnight travel. If omitted with end_time present, defaults to date. Omit when unknown.' ],
            ],
            'time'              => [
                'schema' => [ 'type' => 'string', 'description' => 'Local start time as 24-hour HH:MM, for example 09:30: departure, check-in, pickup, or activity start time. Do not put a UTC timestamp here or invent a time for a date-only booking. Omit when unknown.' ],
            ],
            'end_time'          => [
                'schema' => [ 'type' => 'string', 'description' => 'Local end time as 24-hour HH:MM: arrival, checkout, return, or activity end time. For transport across timezones, use the destination local time. Omit when unknown.' ],
            ],
            'starts_at_utc'     => [
                'schema' => [ 'type' => 'string', 'description' => 'Absolute start timestamp in UTC using ISO 8601, for example 2026-10-03T07:30:00Z. Supply only when given by the source or reliably converted using a known timezone. Keep consistent with date and time; omit when unknown.' ],
            ],
            'ends_at_utc'       => [
                'schema' => [ 'type' => 'string', 'description' => 'Absolute end timestamp in UTC using ISO 8601, for example 2026-10-03T09:00:00Z. Useful for travel across timezones. Keep consistent with end_date and end_time; omit when unknown.' ],
            ],
            'timezone'          => [
                'schema' => [ 'type' => 'string', 'description' => 'IANA timezone identifier for the item, such as Europe/Berlin; not an abbreviation such as CET or a UTC offset. A single timezone cannot describe both ends of travel across timezones; use UTC timestamps for those absolute instants. Omit when unknown.' ],
            ],
            'location'          => [
                'schema' => [ 'type' => 'string', 'description' => 'Departure airport or station for transport, hotel name or address for lodging, pickup location for a rental car, or venue for an activity. Preserve airport codes, station names, and addresses supplied by the user.' ],
            ],
            'end_location'      => [
                'schema' => [ 'type' => 'string', 'description' => 'Arrival airport or station for transport, or return location for a rental car when different from pickup. Omit for a stay or activity at one location.' ],
            ],
            'url'               => [
                'schema' => [ 'type' => 'string', 'description' => 'Full booking, map, source, or reference URL supplied by the user, for example https://example.com/reservation. Omit when none was supplied; do not invent a URL.' ],
            ],
            'app_url'           => [
                'schema' => [ 'type' => 'string', 'description' => 'Travel App URL for this itinerary item.' ],
                'input'  => false,
            ],
            'details'           => [
                'schema' => [ 'type' => 'string', 'description' => 'Plain-text notes useful on the trip overview, such as baggage allowance, check-in instructions, or an activity meeting point. Keep structured dates, times, and locations in their own fields. Exclude payment details and avoid copying the entire confirmation.' ],
            ],
            'url_preview'       => [
                'schema' => self::url_preview_schema(),
                'input'  => false,
            ],
            'url_preview_debug' => [
                'schema' => self::url_preview_schema(),
                'input'  => false,
            ],
            'attachments'       => [
                'schema' => self::attachments_schema(),
                'input'  => false,
            ],
        ];
    }

    private static function schema_properties(): array {
        return array_map( static function( array $definition ): array {
            return $definition['schema'];
        }, self::field_definitions() );
    }

    private static function input_schema_properties(): array {
        $properties = [];
        foreach ( self::field_definitions() as $field => $definition ) {
            if ( false === ( $definition['input'] ?? true ) || 'id' === $field ) {
                continue;
            }

            $properties[ $field ] = $definition['schema'];
        }

        return $properties;
    }

    private static function url_preview_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'title'       => [ 'type' => 'string' ],
                'description' => [ 'type' => 'string' ],
                'image'       => [ 'type' => 'string' ],
                'site_name'   => [ 'type' => 'string' ],
                'url'         => [ 'type' => 'string' ],
            ],
        ];
    }

    private static function attachments_schema(): array {
        return [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'id'       => [ 'type' => 'integer' ],
                    'title'    => [ 'type' => 'string' ],
                    'filename' => [ 'type' => 'string' ],
                    'mime'     => [ 'type' => 'string' ],
                    'size'     => [ 'type' => 'string' ],
                    'url'      => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    public static function normalize( $segment ): array {
        $segment = is_array( $segment ) ? $segment : [];
        $type = sanitize_key( (string) ( $segment['type'] ?? 'other' ) );
        if ( 'hotel' === $type ) {
            $type = 'lodging';
        }
        $date = sanitize_text_field( (string) ( $segment['date'] ?? '' ) );
        $end_date = sanitize_text_field( (string) ( $segment['end_date'] ?? '' ) );
        $end_time = sanitize_text_field( (string) ( $segment['end_time'] ?? '' ) );
        if ( '' === $end_date && '' !== $end_time ) {
            $end_date = $date;
        }

        return [
            'type'          => in_array( $type, self::TYPES, true ) ? $type : 'other',
            'title'         => sanitize_text_field( (string) ( $segment['title'] ?? '' ) ),
            'date'          => $date,
            'end_date'      => $end_date,
            'time'          => sanitize_text_field( (string) ( $segment['time'] ?? '' ) ),
            'end_time'      => $end_time,
            'starts_at_utc' => sanitize_text_field( (string) ( $segment['starts_at_utc'] ?? '' ) ),
            'ends_at_utc'   => sanitize_text_field( (string) ( $segment['ends_at_utc'] ?? '' ) ),
            'timezone'      => sanitize_text_field( (string) ( $segment['timezone'] ?? '' ) ),
            'location'      => sanitize_text_field( (string) ( $segment['location'] ?? '' ) ),
            'end_location'  => sanitize_text_field( (string) ( $segment['end_location'] ?? '' ) ),
            'url'           => esc_url_raw( (string) ( $segment['url'] ?? '' ) ),
            'url_preview'   => ( new UrlPreviewService() )->normalize_preview( $segment['url_preview'] ?? [] ),
            'details'       => sanitize_textarea_field( (string) ( $segment['details'] ?? '' ) ),
        ];
    }

    /**
     * Maps the segment fields of an admin-post request onto a segment array.
     *
     * The nonce is verified by the App handler that calls this - the sniff
     * cannot see across the call - and every field is unslashed and sanitized
     * below.
     *
     * phpcs:disable WordPress.Security.NonceVerification.Missing
     */
    public static function from_request(): array {
        return [
            'type'         => isset( $_POST['segment_type'] ) ? sanitize_key( wp_unslash( $_POST['segment_type'] ) ) : 'other',
            'title'        => isset( $_POST['segment_title'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_title'] ) ) : '',
            'date'         => isset( $_POST['segment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_date'] ) ) : '',
            'end_date'     => isset( $_POST['segment_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_date'] ) ) : '',
            'time'         => isset( $_POST['segment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_time'] ) ) : '',
            'end_time'     => isset( $_POST['segment_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_time'] ) ) : '',
            'location'     => isset( $_POST['segment_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_location'] ) ) : '',
            'end_location' => isset( $_POST['segment_end_location'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_end_location'] ) ) : '',
            'url'          => isset( $_POST['segment_url'] ) ? esc_url_raw( wp_unslash( $_POST['segment_url'] ) ) : '',
            'url_preview'  => self::url_preview_from_request(),
            'details'      => isset( $_POST['segment_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['segment_details'] ) ) : '',
        ];
    }

    public static function url_preview_from_request(): array {
        return [
            'title'       => isset( $_POST['segment_url_preview_title'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_url_preview_title'] ) ) : '',
            'description' => isset( $_POST['segment_url_preview_description'] ) ? sanitize_text_field( wp_unslash( $_POST['segment_url_preview_description'] ) ) : '',
            'image'       => isset( $_POST['segment_url_preview_image'] ) ? esc_url_raw( wp_unslash( $_POST['segment_url_preview_image'] ) ) : '',
        ];
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    public static function get_user_item( int $trip_id, int $item_id ): ?self {
        if ( ! current_user_can( 'read_travel_app_trip', $trip_id ) || $item_id <= 0 ) {
            return null;
        }

        $post = get_post( $item_id );
        if ( ! $post || 'travel_app_item' !== $post->post_type ) {
            return null;
        }

        if ( 'trash' === $post->post_status || ! has_term( $trip_id, 'travel_app_trip', $post ) ) {
            return null;
        }

        return new self( $post, $trip_id );
    }

    public static function get_for_trip( int $trip_id, ?int $user_id = null ): array {
        $user_id = $user_id ?? get_current_user_id();

        $posts = get_posts( [
            'post_type'      => 'travel_app_item',
            'post_status'    => [ 'private', 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Itinerary order is stored as post meta so items remain normal WordPress posts.
            'meta_key'       => '_travel_app_sort',
            'order'          => 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Items are attached to the trip taxonomy; this is the canonical WordPress relationship query.
            'tax_query'      => [
                [
                    'taxonomy' => 'travel_app_trip',
                    'field'    => 'term_id',
                    'terms'    => [ $trip_id ],
                ],
            ],
        ] );

        return array_values( array_map( static function( \WP_Post $post ) use ( $trip_id ): self {
            return new self( $post, $trip_id );
        }, $posts ) );
    }

    public static function get_user_attachment( int $trip_id, int $item_id, int $attachment_id ) {
        $item = self::get_user_item( $trip_id, $item_id );
        if ( ! $item || $attachment_id <= 0 ) {
            return null;
        }

        $attachment = get_post( $attachment_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            return null;
        }

        if ( (int) $attachment->post_parent !== $item->id ) {
            return null;
        }

        return $attachment;
    }

    public function to_array(): array {
        return $this->base_array();
    }

    private static function resolve_trip_id( int $post_id ): int {
        $trip_ids = wp_get_object_terms( $post_id, 'travel_app_trip', [ 'fields' => 'ids' ] );

        return ! is_wp_error( $trip_ids ) && ! empty( $trip_ids ) ? (int) $trip_ids[0] : 0;
    }

    public function attachments(): array {
        $attachments = get_children( [
            'post_parent'    => $this->id,
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

    private function base_array(): array {
        $segment = [];
        foreach ( array_keys( self::schema_properties() ) as $field ) {
            $segment[ $field ] = $this->{$field};
        }

        return $segment;
    }
}
