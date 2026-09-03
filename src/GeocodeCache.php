<?php
namespace Traveler;

/**
 * Remembers the coordinates a browser looked up on Nominatim so the route map
 * does not have to geocode the same itinerary again on the next visit, or on
 * another device. Nominatim allows one request per second, which is what makes
 * an uncached map slow, and the answers do not change.
 */
class GeocodeCache {
    const OPTION = 'traveler_geocode_cache';
    const MAX_LOCATIONS = 500;
    const MAX_CANDIDATES = 5;

    /**
     * Locations differing only in case or spacing are the same place.
     */
    public static function normalize_location( string $location ): string {
        $location = preg_replace( '/\s+/u', ' ', $location );

        return trim( mb_strtolower( trim( (string) $location ), 'UTF-8' ), " \t\n\r\0\x0B," );
    }

    /**
     * Keeps only well-formed candidates: the browser sends these back, so the
     * coordinates, the ranking hint and the label all get checked here.
     *
     * @param mixed $candidates Candidate list as received.
     * @return array<int,array<string,mixed>>
     */
    public static function sanitize_candidates( $candidates ): array {
        if ( ! is_array( $candidates ) ) {
            return [];
        }

        $clean = [];
        foreach ( $candidates as $candidate ) {
            if ( ! is_array( $candidate ) || ! isset( $candidate['lat'], $candidate['lon'] ) ) {
                continue;
            }

            // Nominatim sends coordinates as strings, but anything not numeric would cast to
            // zero and put the waypoint in the Atlantic.
            if ( ! is_numeric( $candidate['lat'] ) || ! is_numeric( $candidate['lon'] ) ) {
                continue;
            }

            $lat = (float) $candidate['lat'];
            $lon = (float) $candidate['lon'];
            if ( ! is_finite( $lat ) || ! is_finite( $lon ) || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 ) {
                continue;
            }

            $importance = isset( $candidate['importance'] ) ? (float) $candidate['importance'] : 0.0;
            $importance = is_finite( $importance ) ? max( 0.0, min( 1.0, $importance ) ) : 0.0;

            $label = isset( $candidate['label'] ) ? (string) $candidate['label'] : '';
            $label = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $label ) : trim( strip_tags( $label ) );

            $clean[] = [
                'lat'        => round( $lat, 6 ),
                'lon'        => round( $lon, 6 ),
                'importance' => round( $importance, 4 ),
                'label'      => mb_substr( $label, 0, 200 ),
            ];

            if ( count( $clean ) >= self::MAX_CANDIDATES ) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Drops the least recently stored locations so one big itinerary cannot
     * grow the option without bound.
     *
     * @param array<string,array<string,mixed>> $cache
     * @return array<string,array<string,mixed>>
     */
    public static function prune( array $cache, int $max = self::MAX_LOCATIONS ): array {
        if ( count( $cache ) <= $max ) {
            return $cache;
        }

        uasort( $cache, static function( $a, $b ) {
            return ( (int) ( $b['time'] ?? 0 ) ) <=> ( (int) ( $a['time'] ?? 0 ) );
        } );

        return array_slice( $cache, 0, $max, true );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function all(): array {
        $cache = get_option( self::OPTION, [] );

        return is_array( $cache ) ? $cache : [];
    }

    /**
     * Coordinates known for the given locations, keyed by the location as it
     * was passed in so the caller can hand them straight to the map.
     *
     * @param string[] $locations
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function get_many( array $locations ): array {
        $cache = self::all();
        $known = [];

        foreach ( $locations as $location ) {
            $key = self::normalize_location( (string) $location );
            if ( '' === $key || ! isset( $cache[ $key ]['candidates'] ) ) {
                continue;
            }

            $candidates = self::sanitize_candidates( $cache[ $key ]['candidates'] );
            if ( $candidates ) {
                $known[ (string) $location ] = $candidates;
            }
        }

        return $known;
    }

    /**
     * Adds the given locations to a cache array, skipping the ones with nothing
     * usable in them, and prunes the result.
     *
     * @param array<string,array<string,mixed>>            $cache     Cache to add to.
     * @param array<string,array<int,array<string,mixed>>> $locations Candidates keyed by location.
     * @return array<string,array<string,mixed>>
     */
    public static function merge( array $cache, array $locations, int $time = 0 ): array {
        $time = $time ?: time();

        foreach ( $locations as $location => $candidates ) {
            $key = self::normalize_location( (string) $location );
            $candidates = is_array( $candidates ) ? self::sanitize_candidates( $candidates ) : [];

            if ( '' === $key || ! $candidates ) {
                continue;
            }

            $cache[ $key ] = [
                'candidates' => $candidates,
                'time'       => $time,
            ];
        }

        return self::prune( $cache );
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     */
    public static function remember( string $location, array $candidates ): void {
        self::remember_many( [ $location => $candidates ] );
    }

    /**
     * One read and one write, however many locations a route map sends back.
     *
     * @param array<string,array<int,array<string,mixed>>> $locations Candidates keyed by location.
     */
    public static function remember_many( array $locations ): void {
        update_option( self::OPTION, self::merge( self::all(), $locations ), false );
    }

    public static function flush(): void {
        delete_option( self::OPTION );
    }
}
