<?php

use PHPUnit\Framework\TestCase;
use Traveler\GeocodeCache;

final class GeocodeCacheTest extends TestCase {
    public function test_normalizes_case_spacing_and_trailing_commas(): void {
        self::assertSame( 'main street 5, springfield', GeocodeCache::normalize_location( "  Main Street 5,\tSpringfield ,\n" ) );
        self::assertSame(
            GeocodeCache::normalize_location( 'Main Street 5, Springfield' ),
            GeocodeCache::normalize_location( 'main street  5, springfield' )
        );
    }

    public function test_keeps_only_usable_candidates(): void {
        $candidates = GeocodeCache::sanitize_candidates( [
            [ 'lat' => '48.2083537', 'lon' => '16.3725042', 'importance' => '0.812', 'label' => 'A place' ],
            [ 'lat' => 91, 'lon' => 0 ],
            [ 'lat' => 0, 'lon' => 200 ],
            [ 'lon' => 5 ],
            'nonsense',
        ] );

        self::assertCount( 1, $candidates );
        self::assertSame( 48.208354, $candidates[0]['lat'] );
        self::assertSame( 16.372504, $candidates[0]['lon'] );
        self::assertSame( 0.812, $candidates[0]['importance'] );
        self::assertSame( 'A place', $candidates[0]['label'] );
    }

    public function test_clamps_importance_and_caps_the_candidate_list(): void {
        $candidate = [ 'lat' => 1, 'lon' => 1, 'importance' => 5 ];
        $candidates = GeocodeCache::sanitize_candidates( array_fill( 0, 12, $candidate ) );

        self::assertCount( GeocodeCache::MAX_CANDIDATES, $candidates );
        self::assertSame( 1.0, $candidates[0]['importance'] );
        self::assertSame( 0.0, GeocodeCache::sanitize_candidates( [ [ 'lat' => 1, 'lon' => 1, 'importance' => -2 ] ] )[0]['importance'] );
    }

    public function test_merge_stores_normalized_locations_and_skips_unusable_ones(): void {
        $cache = GeocodeCache::merge( [], [
            ' Verona, Italy ' => [ [ 'lat' => 45.4384, 'lon' => 10.9916, 'label' => 'Verona' ] ],
            'Nowhere'         => [],
            'Broken'          => [ [ 'lat' => 'north', 'lon' => 'east' ] ],
        ], 1234 );

        self::assertSame( [ 'verona, italy' ], array_keys( $cache ) );
        self::assertSame( 1234, $cache['verona, italy']['time'] );
        self::assertSame( 'Verona', $cache['verona, italy']['candidates'][0]['label'] );
    }

    public function test_merge_replaces_what_was_known_about_a_location(): void {
        $first = GeocodeCache::merge( [], [ 'Chianti' => [ [ 'lat' => 22.2, 'lon' => 113.9, 'label' => 'Chianti, far away' ] ] ], 10 );
        $second = GeocodeCache::merge( $first, [ 'chianti' => [ [ 'lat' => 43.58, 'lon' => 11.31, 'label' => 'Greve in Chianti' ] ] ], 20 );

        self::assertCount( 1, $second );
        self::assertSame( 'Greve in Chianti', $second['chianti']['candidates'][0]['label'] );
    }

    public function test_prune_keeps_the_most_recently_stored_locations(): void {
        $cache = [
            'old'    => [ 'candidates' => [], 'time' => 100 ],
            'newer'  => [ 'candidates' => [], 'time' => 300 ],
            'newest' => [ 'candidates' => [], 'time' => 500 ],
        ];

        self::assertSame( [ 'newest', 'newer' ], array_keys( GeocodeCache::prune( $cache, 2 ) ) );
        self::assertSame( array_keys( $cache ), array_keys( GeocodeCache::prune( $cache, 3 ) ) );
    }
}
