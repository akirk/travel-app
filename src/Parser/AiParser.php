<?php

namespace TravelApp\Parser;

class AiParser {
    public function parse( string $text ) {
        if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
            return new \WP_Error( 'ai_client_unavailable', __( 'WordPress AI Client is unavailable.', 'travel-app' ) );
        }

        $prompt = implode( "\n", [
            'Extract this travel itinerary into strict JSON only.',
            'Use this shape: {"title":"","starts_at":"","ends_at":"","segments":[{"type":"flight|lodging|train|car|activity|other","title":"","date":"","end_date":"","time":"","end_time":"","location":"","end_location":"","url":"","details":""}]}.',
            'Current date and time: ' . $this->get_current_datetime_context() . '. Use this when the source omits a year or uses relative dates.',
            '',
            $text,
        ] );
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

    private function get_current_datetime_context(): string {
        if ( function_exists( 'current_datetime' ) ) {
            return current_datetime()->format( 'Y-m-d H:i:s T P' );
        }

        return ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s T P' );
    }
}
