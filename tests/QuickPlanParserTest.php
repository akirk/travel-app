<?php

use PHPUnit\Framework\TestCase;
use TravelApp\Parser\QuickPlanParser;

final class QuickPlanParserTest extends TestCase {
    public function test_parses_compact_activity_with_trailing_city(): void {
        $segment = ( new QuickPlanParser() )->parse( 'hafenrundfahrt hamburg august 1, 2026 15:00' );

        self::assertSame( 'activity', $segment['type'] );
        self::assertSame( 'Hafenrundfahrt', $segment['title'] );
        self::assertSame( 'Hamburg', $segment['location'] );
        self::assertSame( '2026-08-01', $segment['date'] );
        self::assertSame( '15:00', $segment['time'] );
        self::assertSame( 'hafenrundfahrt hamburg august 1, 2026 15:00', $segment['details'] );
    }

    public function test_parses_natural_in_location_wording_and_12_hour_time(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Miniatur Wunderland in Hamburg on Aug 2nd, 2026 at 3pm' );

        self::assertSame( 'Miniatur Wunderland', $segment['title'] );
        self::assertSame( 'Hamburg', $segment['location'] );
        self::assertSame( '2026-08-02', $segment['date'] );
        self::assertSame( '15:00', $segment['time'] );
    }

    public function test_parses_dash_separated_location_and_european_date(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Dinner - Hamburg 01.08.2026 19.30' );

        self::assertSame( 'Dinner', $segment['title'] );
        self::assertSame( 'Hamburg', $segment['location'] );
        self::assertSame( '2026-08-01', $segment['date'] );
        self::assertSame( '19:30', $segment['time'] );
    }

    public function test_parses_iso_dates(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Flight LH123 at Frankfurt 2026-08-03 06:45' );

        self::assertSame( 'flight', $segment['type'] );
        self::assertSame( '2026-08-03', $segment['date'] );
        self::assertSame( '06:45', $segment['time'] );
    }

    public function test_parses_compact_flight_route_with_time_range(): void {
        $segment = ( new QuickPlanParser() )->parse( 'os123 ber-vie 2026-10-10 15:00-17:00' );

        self::assertSame( 'flight', $segment['type'] );
        self::assertSame( 'OS123', $segment['title'] );
        self::assertSame( 'BER', $segment['location'] );
        self::assertSame( 'VIE', $segment['end_location'] );
        self::assertSame( '2026-10-10', $segment['date'] );
        self::assertSame( '2026-10-10', $segment['end_date'] );
        self::assertSame( '15:00', $segment['time'] );
        self::assertSame( '17:00', $segment['end_time'] );
    }

    public function test_parses_compact_flight_route_with_start_time_only(): void {
        $segment = ( new QuickPlanParser() )->parse( 'os123 ber-vie 2026-10-10 15:00' );

        self::assertSame( 'flight', $segment['type'] );
        self::assertSame( 'OS123', $segment['title'] );
        self::assertSame( 'BER', $segment['location'] );
        self::assertSame( 'VIE', $segment['end_location'] );
        self::assertSame( '2026-10-10', $segment['date'] );
        self::assertSame( '', $segment['end_date'] );
        self::assertSame( '15:00', $segment['time'] );
        self::assertSame( '', $segment['end_time'] );
    }

    public function test_parses_dot_separated_time_range(): void {
        $segment = ( new QuickPlanParser() )->parse( 'LH789 FRA-BER 2026-10-11 8.05-9.20' );

        self::assertSame( 'flight', $segment['type'] );
        self::assertSame( 'LH789', $segment['title'] );
        self::assertSame( 'FRA', $segment['location'] );
        self::assertSame( 'BER', $segment['end_location'] );
        self::assertSame( '08:05', $segment['time'] );
        self::assertSame( '09:20', $segment['end_time'] );
    }

    public function test_parses_slash_dates_as_month_day_year(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Rental car Boston 8/4/26 9am' );

        self::assertSame( 'car', $segment['type'] );
        self::assertSame( '2026-08-04', $segment['date'] );
        self::assertSame( '09:00', $segment['time'] );
    }

    public function test_parses_day_month_year_dates(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Train to Berlin 5 Aug 2026 22:10' );

        self::assertSame( 'train', $segment['type'] );
        self::assertSame( '2026-08-05', $segment['date'] );
        self::assertSame( '22:10', $segment['time'] );
    }

    public function test_parses_lodging_keywords(): void {
        $segment = ( new QuickPlanParser() )->parse( 'Hotel check-in near Munich Aug 6, 2026 4pm' );

        self::assertSame( 'lodging', $segment['type'] );
        self::assertSame( 'Hotel check-in', $segment['title'] );
        self::assertSame( 'Munich', $segment['location'] );
        self::assertSame( '16:00', $segment['time'] );
    }

    public function test_rejects_invalid_dates(): void {
        $parser = new QuickPlanParser();

        self::assertFalse( $parser->looks_like_quick_plan( 'Dinner Hamburg 2026-02-30 19:00' ) );
        self::assertFalse( $parser->looks_like_quick_plan( 'Dinner Hamburg 31.02.2026 19:00' ) );
        self::assertFalse( $parser->looks_like_quick_plan( 'Dinner Hamburg 13/31/2026 19:00' ) );
    }

    public function test_detection_ignores_long_multi_line_confirmations(): void {
        $parser = new QuickPlanParser();

        self::assertTrue( $parser->looks_like_quick_plan( 'Harbor tour in Hamburg August 1, 2026 3pm' ) );
        self::assertFalse( $parser->looks_like_quick_plan( "Booking confirmation\nHotel Hamburg\nAugust 1, 2026" ) );
    }
}
