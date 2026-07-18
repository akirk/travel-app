<?php

use PHPUnit\Framework\TestCase;
use TravelApp\Parser\AiParser;

final class AiParserTest extends TestCase {
    public function test_parses_ai_json_response(): void {
        $GLOBALS['travel_app_generic_parser_response'] = json_encode( [
            'title'     => 'AI Parsed Hamburg',
            'starts_at' => '2026-08-01',
            'ends_at'   => '2026-08-01',
            'segments'  => [
                [
                    'type'     => 'activity',
                    'title'    => 'AI Harbor Tour',
                    'date'     => '2026-08-01',
                    'time'     => '15:00',
                    'location' => 'Hamburg',
                ],
            ],
        ] );

        $parsed = ( new AiParser() )->parse( 'Harbor tour in Hamburg August 1, 2026 3pm' );

        self::assertSame( 'wp-ai-client', $parsed['parser'] );
        self::assertSame( 'AI Parsed Hamburg', $parsed['title'] );
        self::assertSame( 'AI Harbor Tour', $parsed['segments'][0]['title'] );
        self::assertStringContainsString( 'Harbor tour in Hamburg', $GLOBALS['travel_app_generic_parser_last_prompt'] );
    }

    public function test_prompt_includes_current_date_time_context(): void {
        $GLOBALS['travel_app_generic_parser_response'] = json_encode( [
            'title'     => 'Yearless Trip',
            'starts_at' => '',
            'ends_at'   => '',
            'segments'  => [],
        ] );

        ( new AiParser() )->parse( 'Dinner Hamburg 31.3. 19:00' );

        self::assertMatchesRegularExpression(
            '/Current date and time: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [A-Z]{3} [+-]\d{2}:\d{2}\. Use this when the source omits a year or uses relative dates\./',
            $GLOBALS['travel_app_generic_parser_last_prompt']
        );
    }

    public function test_extracts_json_object_from_wrapped_ai_text(): void {
        $GLOBALS['travel_app_generic_parser_response'] = 'Here is the parsed trip: ' . json_encode( [
            'title'     => 'Wrapped AI Trip',
            'starts_at' => '2026-08-01',
            'ends_at'   => '',
            'segments'  => [],
        ] );

        $parsed = ( new AiParser() )->parse( 'Trip text' );

        self::assertSame( 'wp-ai-client', $parsed['parser'] );
        self::assertSame( 'Wrapped AI Trip', $parsed['title'] );
    }

    public function test_returns_error_when_ai_response_has_no_json(): void {
        $GLOBALS['travel_app_generic_parser_response'] = 'not json';

        $parsed = ( new AiParser() )->parse( 'Trip text' );

        self::assertInstanceOf( WP_Error::class, $parsed );
        self::assertSame( 'ai_invalid_json', $parsed->get_error_code() );
    }
}
