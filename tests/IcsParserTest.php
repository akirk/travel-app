<?php

use PHPUnit\Framework\TestCase;
use TravelApp\Parser\IcsParser;

final class IcsParserTest extends TestCase {
    public function test_tripit_activity_uses_local_time_range_and_same_day_end_date(): void {
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

        self::assertIsArray( $segment );
        self::assertSame( 'activity', $segment['type'] );
        self::assertSame( '2026-07-30', $segment['date'] );
        self::assertSame( '2026-07-30', $segment['end_date'] );
        self::assertSame( '09:15', $segment['time'] );
        self::assertSame( '14:00', $segment['end_time'] );
        self::assertSame( '2026-07-30T07:15:00Z', $segment['starts_at_utc'] );
        self::assertSame( '2026-07-30T12:00:00Z', $segment['ends_at_utc'] );
        self::assertSame( 'CEST', $segment['timezone'] );
        self::assertSame( '', $segment['details'] );
    }

    public function test_tripit_lodging_merges_check_in_and_check_out_without_duplicate_details(): void {
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

        self::assertCount( 1, $segments );
        $segment = $segments[0];
        self::assertSame( 'lodging', $segment['type'] );
        self::assertSame( 'Stråtækt Dukkehus fra 1875.', $segment['title'] );
        self::assertSame( '2026-07-25', $segment['date'] );
        self::assertSame( '2026-07-29', $segment['end_date'] );
        self::assertSame( '15:00', $segment['time'] );
        self::assertSame( '10:00', $segment['end_time'] );
        self::assertSame( '2026-07-25T13:00:00Z', $segment['starts_at_utc'] );
        self::assertSame( '2026-07-29T08:00:00Z', $segment['ends_at_utc'] );
        self::assertSame( '', $segment['details'] );
    }

    public function test_tripit_rail_uses_second_local_time_as_arrival_time(): void {
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

        self::assertIsArray( $segment );
        self::assertSame( 'train', $segment['type'] );
        self::assertSame( '2026-07-19', $segment['date'] );
        self::assertSame( '2026-07-20', $segment['end_date'] );
        self::assertSame( '18:36', $segment['time'] );
        self::assertSame( '08:56', $segment['end_time'] );
        self::assertSame( '2026-07-19T16:36:00Z', $segment['starts_at_utc'] );
        self::assertSame( '2026-07-20T06:56:00Z', $segment['ends_at_utc'] );
        self::assertSame( 'CEST', $segment['timezone'] );
        self::assertStringContainsString( 'Wien Hauptbahnhof', $segment['location'] );
        self::assertStringContainsString( 'Hamburg-Harburg', $segment['end_location'] );
        self::assertStringNotContainsString( '18:36', $segment['details'] );
        self::assertStringNotContainsString( '08:56', $segment['details'] );
    }
}
