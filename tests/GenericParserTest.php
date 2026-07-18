<?php

use PHPUnit\Framework\TestCase;
use TravelApp\Parser\GenericParser;

class TravelAppGenericParserTestTextResult {
    private $text;

    public function __construct( string $text ) {
        $this->text = $text;
    }

    public function to_text(): string {
        return $this->text;
    }
}

class TravelAppGenericParserTestBuilder {
    private $response;

    public function __construct( string $response ) {
        $this->response = $response;
    }

    public function using_system_instruction( string $instruction ): self {
        return $this;
    }

    public function using_max_tokens( int $max_tokens ): self {
        return $this;
    }

    public function generate_text_result(): TravelAppGenericParserTestTextResult {
        return new TravelAppGenericParserTestTextResult( $this->response );
    }
}

function wp_ai_client_prompt( string $prompt ) {
    $GLOBALS['travel_app_generic_parser_last_prompt'] = $prompt;

    return new TravelAppGenericParserTestBuilder( $GLOBALS['travel_app_generic_parser_response'] );
}

final class GenericParserTest extends TestCase {
    public function test_uses_ai_client_before_local_parsing_for_quick_looking_text(): void {
        $GLOBALS['travel_app_generic_parser_response'] = json_encode( [
            'title'     => 'AI Parsed Quick Plan',
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

        $parsed = ( new GenericParser() )->parse( 'Harbor tour in Hamburg August 1, 2026 3pm' );

        self::assertSame( 'wp-ai-client', $parsed['parser'] );
        self::assertSame( 'AI Parsed Quick Plan', $parsed['title'] );
    }

    public function test_falls_back_when_ai_returns_invalid_json(): void {
        $GLOBALS['travel_app_generic_parser_response'] = 'not json';

        $parsed = ( new GenericParser() )->parse( 'Hotel reservation August 1, 2026 15:00' );

        self::assertSame( 'fallback', $parsed['parser'] );
        self::assertSame( 'ai_invalid_json', $parsed['parser_error']['code'] );
        self::assertSame( 'The AI response did not include JSON.', $parsed['parser_error']['message'] );
        self::assertNotEmpty( $parsed['segments'] );
    }
}
