<?php

use PHPUnit\Framework\TestCase;
use Traveler\ShareTarget;

final class ShareTargetTest extends TestCase {
    public function test_joins_title_text_and_file_contents(): void {
        $text = ShareTarget::build_text(
            [ 'title' => 'Booking', 'text' => "Hotel in Berlin\n", 'url' => '' ],
            [ "BEGIN:VCALENDAR\nEND:VCALENDAR", '' ]
        );

        self::assertSame( "Booking\n\nHotel in Berlin\n\nBEGIN:VCALENDAR\nEND:VCALENDAR", $text );
    }

    public function test_drops_duplicate_url_sent_as_text(): void {
        $text = ShareTarget::build_text( [ 'text' => 'https://example.com/booking', 'url' => 'https://example.com/booking' ] );

        self::assertSame( 'https://example.com/booking', $text );
    }

    public function test_returns_empty_string_when_nothing_was_shared(): void {
        self::assertSame( '', ShareTarget::build_text( [ 'title' => ' ' ], [ "\n" ] ) );
    }

    public function test_normalizes_single_and_multiple_uploads(): void {
        $single = [ 'name' => 'a.ics', 'type' => 'text/calendar', 'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 10 ];
        self::assertSame( [ $single ], ShareTarget::normalize_files( $single ) );

        $multiple = [
            'name'     => [ 'a.ics', 'b.pdf' ],
            'type'     => [ 'text/calendar', 'application/pdf' ],
            'tmp_name' => [ '/tmp/a', '/tmp/b' ],
            'error'    => [ 0, 0 ],
            'size'     => [ 10, 20 ],
        ];
        $files = ShareTarget::normalize_files( $multiple );
        self::assertCount( 2, $files );
        self::assertSame( 'b.pdf', $files[1]['name'] );
        self::assertSame( 'application/pdf', $files[1]['type'] );
        self::assertSame( '/tmp/b', $files[1]['tmp_name'] );

        self::assertSame( [], ShareTarget::normalize_files( null ) );
        self::assertSame( [], ShareTarget::normalize_files( [] ) );
    }

    public function test_accepts_calendar_and_text_files_only(): void {
        self::assertTrue( ShareTarget::is_text_file( [ 'name' => 'trip.ics', 'type' => 'application/octet-stream' ] ) );
        self::assertTrue( ShareTarget::is_text_file( [ 'name' => 'Trip.ICS', 'type' => '' ] ) );
        self::assertTrue( ShareTarget::is_text_file( [ 'name' => 'invite', 'type' => 'text/calendar; charset=utf-8' ] ) );
        self::assertTrue( ShareTarget::is_text_file( [ 'name' => 'notes.txt', 'type' => 'text/plain' ] ) );

        self::assertFalse( ShareTarget::is_text_file( [ 'name' => 'ticket.pdf', 'type' => 'application/pdf' ] ) );
        self::assertFalse( ShareTarget::is_text_file( [ 'name' => 'ticket.pdf', 'type' => 'text/pdf' ] ) );
        self::assertFalse( ShareTarget::is_text_file( [ 'name' => 'photo.jpg', 'type' => 'image/jpeg' ] ) );
        self::assertFalse( ShareTarget::is_text_file( [] ) );
    }
}
