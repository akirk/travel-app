<?php

namespace TravelApp\Parser;

class GenericParser {
    public function parse( string $text ): array {
        $parsed = ( new AiParser() )->parse( $text );

        if ( is_wp_error( $parsed ) ) {
            return $this->fallback_parse( $text );
        }

        return $parsed;
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
