<?php

namespace TravelApp;

class LodgingCoverage {
    public static function analyze( array $trip_data, array $segments ): array {
        $required_nights = self::required_nights(
            (string) ( $trip_data['starts_at'] ?? '' ),
            (string) ( $trip_data['ends_at'] ?? '' )
        );
        $covered_nights = self::covered_nights( $segments );
        $missing_nights = array_values( array_filter( $required_nights, static function( string $night ) use ( $covered_nights ): bool {
            return empty( $covered_nights[ $night ] );
        } ) );
        $timeline_segments = self::timeline_segments( $segments );

        return [
            'required_nights' => $required_nights,
            'covered_nights'  => array_keys( $covered_nights ),
            'covered_details' => array_values( array_filter( array_map( static function( string $night ) use ( $covered_nights, $timeline_segments ): ?array {
                if ( empty( $covered_nights[ $night ] ) ) {
                    return null;
                }

                $night_date = date_create_immutable( $night );
                $segment = $covered_nights[ $night ];
                $type = (string) ( $segment['type'] ?? '' );
                $location = trim( (string) ( $segment['location'] ?? '' ) );
                $end_location = trim( (string) ( $segment['end_location'] ?? '' ) );

                if ( in_array( $type, [ 'flight', 'train' ], true ) && '' !== $end_location ) {
                    $location = $end_location;
                }

                return [
                    'date'       => $night,
                    'end_date'   => $night_date ? $night_date->modify( '+1 day' )->format( 'Y-m-d' ) : '',
                    'item_id'    => (int) ( $segment['id'] ?? 0 ),
                    'item_title' => (string) ( $segment['title'] ?? '' ),
                    'item_type'  => $type,
                    'location'   => '' !== $location ? $location : self::night_location( $night, $timeline_segments ),
                ];
            }, $required_nights ) ) ),
            'missing_nights'  => $missing_nights,
            'missing_ranges'  => self::missing_ranges( $missing_nights ),
            'missing_details' => array_map( static function( string $night ) use ( $timeline_segments ): array {
                $night_date = date_create_immutable( $night );

                return [
                    'date'     => $night,
                    'end_date' => $night_date ? $night_date->modify( '+1 day' )->format( 'Y-m-d' ) : '',
                    'location' => self::night_location( $night, $timeline_segments ),
                ];
            }, $missing_nights ),
        ];
    }

    public static function timeline_segments( array $segments ): array {
        $timeline_segments = [];

        foreach ( $segments as $segment ) {
            $segment['_index'] = (int) ( $segment['id'] ?? 0 );
            $segment['_sort']  = trim( (string) ( $segment['date'] ?? '' ) . ' ' . (string) ( $segment['time'] ?? '' ) );
            $timeline_segments[] = $segment;

            if ( 'lodging' === ( $segment['type'] ?? '' ) && ! empty( $segment['end_date'] ) ) {
                $checkout_segment = $segment;
                $checkout_segment['date'] = (string) $segment['end_date'];
                $checkout_segment['time'] = (string) ( $segment['end_time'] ?? '' );
                $checkout_segment['title'] = (string) ( $segment['title'] ?? '' );
                $checkout_segment['end_date'] = '';
                $checkout_segment['_timeline_kind'] = 'checkout';
                $checkout_segment['_sort'] = trim( $checkout_segment['date'] . ' ' . $checkout_segment['time'] );
                $timeline_segments[] = $checkout_segment;
            }

            if ( 'car' === ( $segment['type'] ?? '' ) && ! empty( $segment['end_date'] ) ) {
                $return_segment = $segment;
                $return_segment['date'] = (string) $segment['end_date'];
                $return_segment['time'] = (string) ( $segment['end_time'] ?? '' );
                $return_segment['title'] = (string) ( $segment['title'] ?? '' );
                $return_segment['location'] = (string) ( ( $segment['end_location'] ?? '' ) ?: ( $segment['location'] ?? '' ) );
                $return_segment['end_date'] = '';
                $return_segment['end_location'] = '';
                $return_segment['_timeline_kind'] = 'return';
                $return_segment['_sort'] = trim( $return_segment['date'] . ' ' . $return_segment['time'] );
                $timeline_segments[] = $return_segment;
            }
        }

        usort( $timeline_segments, static function( array $a, array $b ): int {
            return strcmp( (string) ( $a['_sort'] ?? '' ), (string) ( $b['_sort'] ?? '' ) );
        } );

        return $timeline_segments;
    }

    private static function required_nights( string $starts_at, string $ends_at ): array {
        $required_nights = [];
        $trip_start_date = '' !== $starts_at ? date_create_immutable( $starts_at ) : false;
        $trip_end_date = '' !== $ends_at ? date_create_immutable( $ends_at ) : false;

        if ( ! $trip_start_date || ! $trip_end_date || $trip_end_date <= $trip_start_date ) {
            return [];
        }

        for ( $night = $trip_start_date; $night < $trip_end_date; $night = $night->modify( '+1 day' ) ) {
            $required_nights[] = $night->format( 'Y-m-d' );
        }

        return $required_nights;
    }

    private static function covered_nights( array $segments ): array {
        $covered_nights = [];

        foreach ( $segments as $segment ) {
            if ( empty( $segment['end_date'] ) || ( 'lodging' !== ( $segment['type'] ?? '' ) && ! self::is_travel_segment( $segment ) ) ) {
                continue;
            }

            $start_date = ! empty( $segment['date'] ) ? date_create_immutable( (string) $segment['date'] ) : false;
            $end_date = date_create_immutable( (string) $segment['end_date'] );
            if ( ! $start_date || ! $end_date || $end_date <= $start_date ) {
                continue;
            }

            for ( $night = $start_date; $night < $end_date; $night = $night->modify( '+1 day' ) ) {
                $covered_nights[ $night->format( 'Y-m-d' ) ] = $segment;
            }
        }

        return $covered_nights;
    }

    private static function missing_ranges( array $missing_nights ): array {
        $ranges = [];

        foreach ( $missing_nights as $missing_night ) {
            $last_range_index = count( $ranges ) - 1;
            if ( $last_range_index >= 0 ) {
                $previous_end = date_create_immutable( (string) $ranges[ $last_range_index ]['end'] );
                if ( $previous_end && $missing_night === $previous_end->format( 'Y-m-d' ) ) {
                    $ranges[ $last_range_index ]['end'] = $previous_end->modify( '+1 day' )->format( 'Y-m-d' );
                    continue;
                }
            }

            $range_start = date_create_immutable( $missing_night );
            if ( $range_start ) {
                $ranges[] = [
                    'start' => $missing_night,
                    'end'   => $range_start->modify( '+1 day' )->format( 'Y-m-d' ),
                ];
            }
        }

        return $ranges;
    }

    private static function night_location( string $night, array $timeline_segments ): string {
        $location = '';

        foreach ( $timeline_segments as $segment ) {
            $segment_date = substr( trim( (string) ( $segment['date'] ?? '' ) ), 0, 10 );
            if ( '' === $segment_date || $segment_date > $night ) {
                continue;
            }

            $segment_location = trim( (string) ( $segment['location'] ?? '' ) );
            $segment_end_location = trim( (string) ( $segment['end_location'] ?? '' ) );
            $type = (string) ( $segment['type'] ?? '' );

            if ( in_array( $type, [ 'flight', 'train', 'car' ], true ) && '' !== $segment_end_location ) {
                $location = $segment_end_location;
            } elseif ( '' !== $segment_location ) {
                $location = $segment_location;
            }
        }

        return $location;
    }

    private static function is_travel_segment( array $segment ): bool {
        $type = (string) ( $segment['type'] ?? '' );
        return in_array( $type, [ 'flight', 'train' ], true );
    }
}
