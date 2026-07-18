<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Parser/QuickPlanParser.php';

use TravelApp\Parser\QuickPlanParser;

$test( 'Quick plan parses compact activity with trailing city', static function() use ( $assert ): void {
    $segment = ( new QuickPlanParser() )->parse( 'hafenrundfahrt hamburg august 1, 2026 15:00' );

    $assert( 'activity' === $segment['type'], 'quick plan type was not activity' );
    $assert( 'Hafenrundfahrt' === $segment['title'], 'quick plan title was not parsed' );
    $assert( 'Hamburg' === $segment['location'], 'quick plan location was not parsed' );
    $assert( '2026-08-01' === $segment['date'], 'quick plan date was not parsed' );
    $assert( '15:00' === $segment['time'], 'quick plan time was not parsed' );
    $assert( 'hafenrundfahrt hamburg august 1, 2026 15:00' === $segment['details'], 'quick plan original text was not preserved' );
} );

$test( 'Quick plan parses natural in-location wording and 12 hour time', static function() use ( $assert ): void {
    $segment = ( new QuickPlanParser() )->parse( 'Miniatur Wunderland in Hamburg on Aug 2nd, 2026 at 3pm' );

    $assert( 'Miniatur Wunderland' === $segment['title'], 'in-location title was not parsed' );
    $assert( 'Hamburg' === $segment['location'], 'in-location location was not parsed' );
    $assert( '2026-08-02' === $segment['date'], 'ordinal month date was not parsed' );
    $assert( '15:00' === $segment['time'], '12 hour time was not normalized' );
} );

$test( 'Quick plan parses dash separated location and European date', static function() use ( $assert ): void {
    $segment = ( new QuickPlanParser() )->parse( 'Dinner - Hamburg 01.08.2026 19.30' );

    $assert( 'Dinner' === $segment['title'], 'dash title was not parsed' );
    $assert( 'Hamburg' === $segment['location'], 'dash location was not parsed' );
    $assert( '2026-08-01' === $segment['date'], 'dotted date was not parsed as day-month-year' );
    $assert( '19:30' === $segment['time'], 'dot time was not normalized' );
} );

$test( 'Quick plan detection ignores long multi-line confirmations', static function() use ( $assert ): void {
    $parser = new QuickPlanParser();

    $assert( $parser->looks_like_quick_plan( 'Harbor tour in Hamburg August 1, 2026 3pm' ), 'simple dated line was not detected' );
    $assert( ! $parser->looks_like_quick_plan( "Booking confirmation\nHotel Hamburg\nAugust 1, 2026" ), 'multi-line confirmation was detected as quick plan' );
} );

$finish();
