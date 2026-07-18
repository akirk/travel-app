<?php

namespace TravelApp\Parser;

class GenericParser {
    public function parse( string $text ): array {
        $parsed = $this->parse_with_wp_ai_client( $text );

        if ( is_wp_error( $parsed ) ) {
            return $this->fallback_parse( $text );
        }

        return $parsed;
    }

    private function parse_with_wp_ai_client( string $text ) {
        if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
            return new \WP_Error( 'ai_client_unavailable', __( 'WordPress AI Client is unavailable.', 'travel-app' ) );
        }

        $prompt = 'Extract this travel itinerary into strict JSON only. Use this shape: {"title":"","starts_at":"","ends_at":"","segments":[{"type":"flight|lodging|train|car|activity|other","title":"","date":"","end_date":"","time":"","end_time":"","location":"","end_location":"","url":"","details":""}]}.' . "\n\n" . $text;
        $builder = wp_ai_client_prompt( $prompt );

        if ( is_wp_error( $builder ) ) {
            return $builder;
        }

        if ( method_exists( $builder, 'using_system_instruction' ) ) {
            $builder = $builder->using_system_instruction( 'You extract pasted travel confirmations. Return JSON only. Do not wrap the JSON in Markdown.' );
        }

        if ( method_exists( $builder, 'using_max_tokens' ) ) {
            $builder = $builder->using_max_tokens( 2048 );
        }

        $text_result = $this->generate_ai_text( $builder );

        if ( is_wp_error( $text_result ) ) {
            return $text_result;
        }

        $json = $this->extract_json_object( $text_result );
        if ( '' === $json ) {
            return new \WP_Error( 'ai_invalid_json', __( 'The AI response did not include JSON.', 'travel-app' ) );
        }

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'ai_invalid_json', __( 'The AI response JSON could not be parsed.', 'travel-app' ) );
        }

        $data['parser'] = 'wp-ai-client';

        return $data;
    }

    private function generate_ai_text( $builder ) {
        if ( method_exists( $builder, 'generate_text_result' ) ) {
            $result = $builder->generate_text_result();
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            if ( is_object( $result ) && method_exists( $result, 'to_text' ) ) {
                return $result->to_text();
            }
            if ( is_object( $result ) && method_exists( $result, 'toText' ) ) {
                return $result->toText();
            }
        }

        if ( method_exists( $builder, 'generateTextResult' ) ) {
            $result = $builder->generateTextResult();
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            if ( is_object( $result ) && method_exists( $result, 'toText' ) ) {
                return $result->toText();
            }
        }

        if ( method_exists( $builder, 'generate_text' ) ) {
            return $builder->generate_text();
        }

        return new \WP_Error( 'ai_text_generation_unavailable', __( 'The AI connector does not support text generation.', 'travel-app' ) );
    }

    private function extract_json_object( string $text ): string {
        $text = trim( $text );

        if ( 0 === strpos( $text, '{' ) && strrpos( $text, '}' ) === strlen( $text ) - 1 ) {
            return $text;
        }

        $start = strpos( $text, '{' );
        $end = strrpos( $text, '}' );

        if ( false === $start || false === $end || $end <= $start ) {
            return '';
        }

        return substr( $text, $start, $end - $start + 1 );
    }

    private function fallback_parse( string $text ): array {
        $lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $text ) ) ) );
        $title = $this->first_matching_line( $lines, '/\b(confirmation|reservation|booking|itinerary|flight|hotel|train)\b/i' );
        $dates = $this->extract_dates( $text );

        if ( '' === $title ) {
            $title = __( 'Imported Travel Plan', 'travel-app' );
        }

        $segments = [];
        foreach ( $lines as $line ) {
            if ( preg_match( '/\b(flight|depart|arrival|hotel|check-in|checkout|check-out|train|rental|reservation)\b/i', $line ) ) {
                $segments[] = [
                    'type'     => $this->infer_segment_type( $line ),
                    'title'    => $line,
                    'date'     => $this->extract_first_date( $line ),
                    'time'     => $this->extract_first_time( $line ),
                    'location' => '',
                    'details'  => '',
                ];
            }
        }

        if ( empty( $segments ) ) {
            $segments[] = [
                'type'     => 'other',
                'title'    => $title,
                'date'     => $dates[0] ?? '',
                'time'     => '',
                'location' => '',
                'details'  => wp_trim_words( $text, 40, '' ),
            ];
        }

        return [
            'title'       => $title,
            'starts_at'   => $dates[0] ?? '',
            'ends_at'     => $dates ? end( $dates ) : '',
            'segments'    => $segments,
            'parser'      => 'fallback',
        ];
    }

    private function first_matching_line( array $lines, string $pattern ): string {
        foreach ( $lines as $line ) {
            if ( preg_match( $pattern, $line ) ) {
                return $line;
            }
        }

        return '';
    }

    private function extract_dates( string $text ): array {
        preg_match_all( '/\b(?:\d{4}-\d{2}-\d{2}|\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4})\b/i', $text, $matches );
        return array_values( array_unique( $matches[0] ?? [] ) );
    }

    private function extract_first_date( string $text ): string {
        $dates = $this->extract_dates( $text );
        return $dates[0] ?? '';
    }

    private function extract_first_time( string $text ): string {
        if ( preg_match( '/\b\d{1,2}:\d{2}\s*(?:am|pm)?\b/i', $text, $match ) ) {
            return $match[0];
        }

        return '';
    }

    private function infer_segment_type( string $text ): string {
        if ( preg_match( '/\b(lodging|hotel|check-in|checkout|check-out|airbnb|boardinghouse)\b/i', $text ) ) {
            return 'lodging';
        }
        if ( preg_match( '/\b(flight|airport|airline|boarding pass)\b/i', $text ) ) {
            return 'flight';
        }
        if ( preg_match( '/\b(train|rail)\b/i', $text ) ) {
            return 'train';
        }
        if ( preg_match( '/\b(car|rental)\b/i', $text ) ) {
            return 'car';
        }

        return 'other';
    }
}
