<?php

namespace TravelApp;

class Trip {
    public int $id;
    public string $title;
    public string $starts_at;
    public string $ends_at;
    public string $parser;
    public array $parser_error;
    private \WP_Term $term;
    private ?int $segments_user_id;

    public function __construct( \WP_Term $term, ?int $segments_user_id = null ) {
        $this->term = $term;
        $this->id = (int) $term->term_id;
        $this->title = (string) $term->name;
        $this->starts_at = (string) get_term_meta( $this->id, '_travel_app_starts_at', true );
        $this->ends_at = (string) get_term_meta( $this->id, '_travel_app_ends_at', true );
        $this->parser = (string) get_term_meta( $this->id, '_travel_app_parser', true );
        $this->parser_error = self::normalize_parser_error( get_term_meta( $this->id, '_travel_app_parser_error', true ) );
        $this->segments_user_id = $segments_user_id;
    }

    public static function schema(): array {
        $properties = self::schema_properties();
        $properties['segment_count'] = [ 'type' => 'integer' ];
        $properties['segments'] = ItineraryItem::array_schema();
        $properties['url'] = [ 'type' => 'string' ];
        $properties['share_urls'] = self::share_urls_schema();
        $properties['missing_fields'] = self::missing_fields_schema();

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    private static function schema_properties(): array {
        return [
            'id'           => [ 'type' => 'integer' ],
            'title'        => [ 'type' => 'string' ],
            'starts_at'    => [ 'type' => 'string' ],
            'ends_at'      => [ 'type' => 'string' ],
            'parser'       => [ 'type' => 'string' ],
            'parser_error' => self::parser_error_schema(),
        ];
    }

    public static function parser_error_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'code'    => [ 'type' => 'string' ],
                'message' => [ 'type' => 'string' ],
            ],
        ];
    }

    public static function share_urls_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'fellow' => [ 'type' => 'string' ],
                'public' => [ 'type' => 'string' ],
            ],
        ];
    }

    public static function missing_fields_schema(): array {
        return [
            'type'  => 'array',
            'items' => [
                'type'       => 'object',
                'properties' => [
                    'item_id'    => [ 'type' => 'integer' ],
                    'item_title' => [ 'type' => 'string' ],
                    'field'      => [ 'type' => 'string' ],
                    'label'      => [ 'type' => 'string' ],
                    'reason'     => [ 'type' => 'string' ],
                ],
            ],
        ];
    }

    public static function from_term( $term, ?int $segments_user_id = null ): ?self {
        if ( is_numeric( $term ) ) {
            $term = get_term( (int) $term, 'travel_app_trip' );
        }

        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return new self( $term, $segments_user_id );
    }

    public static function for_current_user(): array {
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

        return array_values( array_map( static function( \WP_Term $term ): self {
            return new self( $term );
        }, $terms ) );
    }

    public static function get( int $trip_id ): ?self {
        if ( $trip_id <= 0 ) {
            return null;
        }

        $term = get_term( $trip_id, 'travel_app_trip' );
        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        return new self( $term );
    }

    public static function get_owner_id( int $trip_id ): int {
        return (int) get_term_meta( $trip_id, '_travel_app_user_id', true );
    }

    public function owner_id(): int {
        return self::get_owner_id( $this->id );
    }

    public function with_segments_user_id( ?int $user_id ): self {
        return new self( $this->term, $user_id );
    }

    public function to_array(): array {
        $segments = array_map( static function( ItineraryItem $item ): array {
            return $item->to_array();
        }, ItineraryItem::get_for_trip( $this->id, $this->segments_user_id ) );

        $trip = [];
        foreach ( array_keys( self::schema_properties() ) as $field ) {
            $trip[ $field ] = $this->{$field};
        }

        $trip['segments'] = $segments;
        $trip['segment_count'] = count( $segments );

        return $trip;
    }

    public function to_ability_array( callable $share_url_callback ): array {
        $trip = $this->to_array();
        $trip['url'] = home_url( '/travel-app/trip/' . $this->id . '/' );
        $trip['share_urls'] = [
            'fellow' => (string) $share_url_callback( $this->id, 'fellow' ),
            'public' => (string) $share_url_callback( $this->id, 'public' ),
        ];
        $trip['missing_fields'] = $this->missing_fields( $trip );

        return $trip;
    }

    public static function normalize_parser_error( $error ): array {
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

    public function missing_fields( ?array $trip = null ): array {
        $trip = $trip ?? $this->to_array();
        $parser = (string) ( $trip['parser'] ?? '' );
        $parser_error = isset( $trip['parser_error'] ) && is_array( $trip['parser_error'] ) ? $trip['parser_error'] : [];
        $parser_error_code = (string) ( $parser_error['code'] ?? '' );
        $parser_error_message = (string) ( $parser_error['message'] ?? '' );
        $reason = __( 'No value is saved for this field.', 'travel-app' );

        if ( '' !== $parser ) {
            $reason = __( 'The parser did not find a value for this field in the imported text.', 'travel-app' );
        }

        if ( '' !== $parser_error_code || '' !== $parser_error_message ) {
            $reason = trim(
                sprintf(
                    /* translators: 1: parser error code, 2: parser error message. */
                    __( 'The parser reported %1$s %2$s, and no value was saved for this field.', 'travel-app' ),
                    $parser_error_code,
                    $parser_error_message
                )
            );
        }

        $field_labels = [
            'title'        => __( 'Title', 'travel-app' ),
            'date'         => __( 'Start Date', 'travel-app' ),
            'time'         => __( 'Start Time', 'travel-app' ),
            'location'     => __( 'Location', 'travel-app' ),
            'end_location' => __( 'End Location', 'travel-app' ),
        ];
        $report = [];

        foreach ( (array) ( $trip['segments'] ?? [] ) as $segment ) {
            if ( ! is_array( $segment ) ) {
                continue;
            }

            $fields = [ 'title', 'date', 'time', 'location' ];
            if ( in_array( (string) ( $segment['type'] ?? '' ), [ 'flight', 'train', 'car' ], true ) ) {
                $fields[] = 'end_location';
            }

            foreach ( $fields as $field ) {
                $value = trim( (string) ( $segment[ $field ] ?? '' ) );
                if ( 'title' === $field && __( 'Untitled item', 'travel-app' ) === $value ) {
                    $value = '';
                }

                if ( '' !== $value ) {
                    continue;
                }

                $report[] = [
                    'item_id'    => (int) ( $segment['id'] ?? 0 ),
                    'item_title' => (string) ( $segment['title'] ?? '' ),
                    'field'      => $field,
                    'label'      => (string) ( $field_labels[ $field ] ?? $field ),
                    'reason'     => $reason,
                ];
            }
        }

        return $report;
    }
}
