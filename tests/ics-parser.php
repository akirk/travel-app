<?php

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}

require_once __DIR__ . '/../src/Parser/IcsParser.php';

use TravelApp\Parser\IcsParser;

$failures = [];

$test = static function( string $name, callable $callback ) use ( &$failures ): void {
    try {
        $callback();
        echo "PASS $name\n";
    } catch ( Throwable $e ) {
        $failures[] = "$name: " . $e->getMessage();
        echo "FAIL $name\n";
    }
};

$assert = static function( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
};

$test( 'TripIt activity uses local time range and same-day end date', static function() use ( $assert ): void {
    $ics = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
DTSTAMP:20260717T111002Z
DTEND:20260730T120000Z
LOCATION:Miniatur Wunderland
UID:item-6468296b-843d-9000-0003-000147891669@tripit.com
DTSTART:20260730T071500Z
SUMMARY:Miniatur Wunderland
DESCRIPTION:View and/or edit details in TripIt : https://www.tripit.com/trip/show/id/378867391\\n \\n\\nThu\\, Jul 30\\n09:15  CEST\\n[Activity] Miniatur Wunderland\\n09:15 to 14:00\\n \\n\\n \\nTripIt - organize your travel at https://www.tripit.com
END:VEVENT
END:VCALENDAR
ICS;

    $parsed = ( new IcsParser() )->parse( $ics );
    $segment = $parsed['segments'][0] ?? null;

    $assert( is_array( $segment ), 'segment was not parsed' );
    $assert( 'activity' === $segment['type'], 'activity type was not inferred' );
    $assert( '2026-07-30' === $segment['date'], 'start date was not parsed' );
    $assert( '2026-07-30' === $segment['end_date'], 'same-day end date was not preserved' );
    $assert( '09:15' === $segment['time'], 'local start time was not used' );
    $assert( '14:00' === $segment['end_time'], 'local end time was not used' );
    $assert( '2026-07-30T07:15:00Z' === $segment['starts_at_utc'], 'UTC start instant was not preserved' );
    $assert( '2026-07-30T12:00:00Z' === $segment['ends_at_utc'], 'UTC end instant was not preserved' );
    $assert( 'CEST' === $segment['timezone'], 'timezone abbreviation was not parsed' );
    $assert( '' === $segment['details'], 'parsed TripIt time lines remained in details' );
} );

$test( 'TripIt lodging merges check-in and check-out without duplicate details', static function() use ( $assert ): void {
    $ics = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
DTSTART:20260725T130000Z
DTEND:20260725T130000Z
LOCATION:Søndervig Landevej 18 Ringkøbing\\, 6950
UID:item-checkin@tripit.com
SUMMARY:Check-in: Stråtækt Dukkehus fra 1875.
DESCRIPTION:Sat\\, Jul 25\\n15:00  CEST\\n[Lodging] Arrive Stråtækt Dukkehus fra 1875.\\nCheck-In: 15:00
END:VEVENT
BEGIN:VEVENT
DTSTART:20260729T080000Z
DTEND:20260729T080000Z
LOCATION:Søndervig Landevej 18 Ringkøbing\\, 6950
UID:item-checkout@tripit.com
SUMMARY:Check-out: Stråtækt Dukkehus fra 1875.
DESCRIPTION:Wed\\, Jul 29\\n10:00  CEST\\n[Lodging] Depart Stråtækt Dukkehus fra 1875.\\nCheck-Out: 10:00
END:VEVENT
END:VCALENDAR
ICS;

    $parsed = ( new IcsParser() )->parse( $ics );
    $segments = $parsed['segments'];

    $assert( 1 === count( $segments ), 'check-in and check-out were not merged' );
    $segment = $segments[0];
    $assert( 'lodging' === $segment['type'], 'lodging type was not inferred' );
    $assert( 'Stråtækt Dukkehus fra 1875.' === $segment['title'], 'lodging title was not normalized' );
    $assert( '2026-07-25' === $segment['date'], 'check-in date was not preserved' );
    $assert( '2026-07-29' === $segment['end_date'], 'check-out date was not preserved' );
    $assert( '15:00' === $segment['time'], 'check-in time was not preserved' );
    $assert( '10:00' === $segment['end_time'], 'check-out time was not preserved' );
    $assert( '2026-07-25T13:00:00Z' === $segment['starts_at_utc'], 'check-in UTC instant was not preserved' );
    $assert( '2026-07-29T08:00:00Z' === $segment['ends_at_utc'], 'check-out UTC instant was not preserved' );
    $assert( '' === $segment['details'], 'parsed lodging lines remained in details' );
} );

$test( 'TripIt rail uses second local time as arrival time', static function() use ( $assert ): void {
    $ics = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
DTSTAMP:20260717T111002Z
DTEND:20260720T065600Z
LOCATION:Wien Hauptbahnhof\\; Am Hbf 1\\, 1100 Wien\\, Austria\\, Wien Hauptbahnhof
UID:item-5c59ec0e-0ac6-9000-0003-00013f708239@tripit.com
DTSTART:20260719T163600Z
SUMMARY:ÖBB - Wien Hauptbahnhof to Hamburg-Harburg
DESCRIPTION:View and/or edit details in TripIt : https://www.tripit.com/trip/show/id/378867391\\n \\n\\nSun\\, Jul 19\\n18:36  CEST\\n[Rail] ÖBB - Wien Hauptbahnhof to Hamburg-Harburg\\nDepart Wien Hauptbahnhof Am Hbf 1\\, 1100 Wien\\, Austria\\, Wien Hauptbahnhof\\n \\nMon\\, Jul 20\\n08:56  CEST\\nArrive Hamburg-Harburg Hannoversche Str. 85\\, 21079 Hamburg\\, Germany\\, Hamburg-Harburg\\n \\n\\nTripIt - organize your travel at https://www.tripit.com
END:VEVENT
END:VCALENDAR
ICS;

    $parsed = ( new IcsParser() )->parse( $ics );
    $segment = $parsed['segments'][0] ?? null;

    $assert( is_array( $segment ), 'segment was not parsed' );
    $assert( 'train' === $segment['type'], 'rail type was not inferred as train' );
    $assert( '2026-07-19' === $segment['date'], 'departure date was not parsed' );
    $assert( '2026-07-20' === $segment['end_date'], 'arrival date was not parsed' );
    $assert( '18:36' === $segment['time'], 'local departure time was not used' );
    $assert( '08:56' === $segment['end_time'], 'local arrival time was not used' );
    $assert( '2026-07-19T16:36:00Z' === $segment['starts_at_utc'], 'UTC departure instant was not preserved' );
    $assert( '2026-07-20T06:56:00Z' === $segment['ends_at_utc'], 'UTC arrival instant was not preserved' );
    $assert( 'CEST' === $segment['timezone'], 'timezone abbreviation was not parsed' );
    $assert( false !== strpos( $segment['location'], 'Wien Hauptbahnhof' ), 'departure location was not parsed' );
    $assert( false !== strpos( $segment['end_location'], 'Hamburg-Harburg' ), 'arrival location was not parsed' );
    $assert( false === strpos( $segment['details'], '18:36' ), 'departure time remained in details' );
    $assert( false === strpos( $segment['details'], '08:56' ), 'arrival time remained in details' );
} );

if ( $failures !== [] ) {
    echo "\n";
    foreach ( $failures as $failure ) {
        echo "$failure\n";
    }
    exit( 1 );
}
