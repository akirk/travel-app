<?php

use PHPUnit\Framework\TestCase;
use TravelApp\Parser\QuickPlanParser;

final class QuickPlanParserTest extends TestCase {
    /** @dataProvider quick_plans */
    public function test_parses_quick_plan( string $text, array $expected ): void {
        $segment = ( new QuickPlanParser() )->parse( $text );

        foreach ( $expected as $field => $value ) {
            self::assertSame( $value, $segment[ $field ], $field );
        }
    }

    // Each named case preserves a supported date/time or route notation.
    public static function quick_plans(): array {
        return [
            'compact_activity_with_trailing_city' => [
                'hafenrundfahrt hamburg august 1, 2026 15:00',
                [ 'type' => 'activity', 'title' => 'Hafenrundfahrt', 'location' => 'Hamburg', 'date' => '2026-08-01', 'time' => '15:00', 'details' => 'hafenrundfahrt hamburg august 1, 2026 15:00' ],
            ],
            'natural_in_location_wording_and_12_hour_time' => [
                'Miniatur Wunderland in Hamburg on Aug 2nd, 2026 at 3pm',
                [ 'title' => 'Miniatur Wunderland', 'location' => 'Hamburg', 'date' => '2026-08-02', 'time' => '15:00' ],
            ],
            'dash_separated_location_and_european_date' => [
                'Dinner - Hamburg 01.08.2026 19.30',
                [ 'title' => 'Dinner', 'location' => 'Hamburg', 'date' => '2026-08-01', 'time' => '19:30' ],
            ],
            'dotted_day_month_date_with_current_year' => [
                'Dinner Hamburg 31.3. 19:00',
                [ 'title' => 'Dinner', 'location' => 'Hamburg', 'date' => gmdate( 'Y' ) . '-03-31', 'time' => '19:00' ],
            ],
            'month_name_date_with_current_year' => [
                'Dinner, August 21, 6pm',
                [ 'title' => 'Dinner', 'date' => gmdate( 'Y' ) . '-08-21', 'time' => '18:00' ],
            ],
            'iso_dates' => [
                'Flight LH123 at Frankfurt 2026-08-03 06:45',
                [ 'type' => 'flight', 'date' => '2026-08-03', 'time' => '06:45' ],
            ],
            'compact_flight_route_with_time_range' => [
                'os123 ber-vie 2026-10-10 15:00-17:00',
                [ 'type' => 'flight', 'title' => 'OS123', 'location' => 'BER', 'end_location' => 'VIE', 'date' => '2026-10-10', 'end_date' => '2026-10-10', 'time' => '15:00', 'end_time' => '17:00' ],
            ],
            'compact_flight_route_with_start_time_only' => [
                'os123 ber-vie 2026-10-10 15:00',
                [ 'type' => 'flight', 'title' => 'OS123', 'location' => 'BER', 'end_location' => 'VIE', 'date' => '2026-10-10', 'end_date' => '', 'time' => '15:00', 'end_time' => '' ],
            ],
            'dot_separated_time_range' => [
                'LH789 FRA-BER 2026-10-11 8.05-9.20',
                [ 'type' => 'flight', 'title' => 'LH789', 'location' => 'FRA', 'end_location' => 'BER', 'time' => '08:05', 'end_time' => '09:20' ],
            ],
            'slash_dates_as_month_day_year' => [
                'Rental car Boston 8/4/26 9am',
                [ 'type' => 'car', 'date' => '2026-08-04', 'time' => '09:00' ],
            ],
            'day_month_year_dates' => [
                'Train to Berlin 5 Aug 2026 22:10',
                [ 'type' => 'train', 'date' => '2026-08-05', 'time' => '22:10' ],
            ],
            'lodging_keywords' => [
                'Hotel check-in near Munich Aug 6, 2026 4pm',
                [ 'type' => 'lodging', 'title' => 'Hotel check-in', 'location' => 'Munich', 'time' => '16:00' ],
            ],
        ];
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
