<?php

namespace TravelApp\Parser;

class QuickPlanParser {
    public function looks_like_quick_plan( string $text ): bool {
        $text = trim( $text );
        if ( '' === $text || false !== strpos( $text, "\n" ) || strlen( $text ) > 220 ) {
            return false;
        }

        $parsed = $this->parse( $text );

        return '' !== $parsed['date'] && '' !== $parsed['title'];
    }

    public function parse( string $text ): array {
        $original = $this->normalize_spaces( $text );
        $working = $original;
        $date = '';
        $time = '';
        $end_time = '';

        $date_match = $this->extract_date_match( $working );
        if ( $date_match ) {
            $date = $date_match['date'];
            $working = substr_replace( $working, ' ', $date_match['offset'], strlen( $date_match['text'] ) );
        }

        $time_range_match = $this->extract_time_range_match( $working );
        if ( $time_range_match ) {
            $time = $time_range_match['time'];
            $end_time = $time_range_match['end_time'];
            $working = substr_replace( $working, ' ', $time_range_match['offset'], strlen( $time_range_match['text'] ) );
        } else {
            $time_match = $this->extract_time_match( $working );
            if ( $time_match ) {
                $time = $time_match['time'];
                $working = substr_replace( $working, ' ', $time_match['offset'], strlen( $time_match['text'] ) );
            }
        }

        $working = $this->normalize_spaces( preg_replace( '/\b(?:on|at)\b/i', ' ', $working ) );
        $title_location = $this->split_title_location( $working );

        return [
            'type'         => $this->infer_type( $title_location['title'] ),
            'title'        => $this->title_case( $title_location['title'] ?: $working ),
            'date'         => $date,
            'end_date'     => '' !== $end_time ? $date : '',
            'time'         => $time,
            'end_time'     => $end_time,
            'location'     => $this->title_case( $title_location['location'] ),
            'end_location' => $this->title_case( $title_location['end_location'] ?? '' ),
            'url'          => '',
            'details'      => $original,
        ];
    }

    private function extract_date_match( string $text ): array {
        $patterns = [
            '/\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?(?:,)?\s+\d{4}\b/i',
            '/\b\d{1,2}(?:st|nd|rd|th)?\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?(?:,)?\s+\d{4}\b/i',
            '/\b\d{4}-\d{1,2}-\d{1,2}\b/',
            '/\b\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\b/',
        ];

        foreach ( $patterns as $pattern ) {
            if ( ! preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
                continue;
            }

            $date_text = preg_replace( '/(\d)(st|nd|rd|th)\b/i', '$1', $match[0][0] );
            $date = $this->normalize_date( (string) $date_text );
            if ( '' === $date ) {
                continue;
            }

            return [
                'text'   => $match[0][0],
                'offset' => $match[0][1],
                'date'   => $date,
            ];
        }

        return [];
    }

    private function normalize_date( string $date_text ): string {
        $date_text = trim( $date_text );

        if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date_text, $match ) ) {
            return checkdate( (int) $match[2], (int) $match[3], (int) $match[1] )
                ? sprintf( '%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3] )
                : '';
        }

        if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $date_text, $match ) ) {
            $year = (int) $match[3];
            $year = $year < 100 ? 2000 + $year : $year;
            return checkdate( (int) $match[2], (int) $match[1], $year )
                ? sprintf( '%04d-%02d-%02d', $year, (int) $match[2], (int) $match[1] )
                : '';
        }

        if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date_text, $match ) ) {
            $year = (int) $match[3];
            $year = $year < 100 ? 2000 + $year : $year;
            return checkdate( (int) $match[1], (int) $match[2], $year )
                ? sprintf( '%04d-%02d-%02d', $year, (int) $match[1], (int) $match[2] )
                : '';
        }

        $timestamp = strtotime( $date_text . ' 12:00:00 UTC' );
        return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
    }

    private function extract_time_range_match( string $text ): array {
        if ( ! preg_match( '/\b((?:[01]?\d|2[0-3])[:.][0-5]\d\s*(?:am|pm)?)\s*-\s*((?:[01]?\d|2[0-3])[:.][0-5]\d\s*(?:am|pm)?)\b/i', $text, $match, PREG_OFFSET_CAPTURE ) ) {
            return [];
        }

        $time = $this->normalize_time( $match[1][0] );
        $end_time = $this->normalize_time( $match[2][0] );
        if ( '' === $time || '' === $end_time ) {
            return [];
        }

        return [
            'text'     => $match[0][0],
            'offset'   => $match[0][1],
            'time'     => $time,
            'end_time' => $end_time,
        ];
    }

    private function extract_time_match( string $text ): array {
        $patterns = [
            '/\b(?:[01]?\d|2[0-3])[:.][0-5]\d\s*(?:am|pm)?\b/i',
            '/\b(?:1[0-2]|0?[1-9])\s*(?:am|pm)\b/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( ! preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
                continue;
            }

            $normalized = $this->normalize_time( $match[0][0] );
            if ( '' === $normalized ) {
                continue;
            }

            return [
                'text'   => $match[0][0],
                'offset' => $match[0][1],
                'time'   => $normalized,
            ];
        }

        return [];
    }

    private function normalize_time( string $time ): string {
        $time = strtolower( trim( str_replace( '.', ':', $time ) ) );
        if ( preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)$/', $time, $match ) ) {
            $hour = (int) $match[1];
            $minute = isset( $match[2] ) && '' !== $match[2] ? (int) $match[2] : 0;
            if ( 'pm' === $match[3] && $hour < 12 ) {
                $hour += 12;
            }
            if ( 'am' === $match[3] && 12 === $hour ) {
                $hour = 0;
            }
            return sprintf( '%02d:%02d', $hour, $minute );
        }

        if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $match ) ) {
            return sprintf( '%02d:%02d', (int) $match[1], (int) $match[2] );
        }

        return '';
    }

    private function split_title_location( string $text ): array {
        $text = trim( $text, " \t\n\r\0\x0B,-" );
        if ( '' === $text ) {
            return [ 'title' => '', 'location' => '' ];
        }

        if ( preg_match( '/^([a-z]{2}\d{1,4})\s+([a-z]{3})-([a-z]{3})$/i', $text, $match ) ) {
            return [
                'title'        => strtoupper( $match[1] ),
                'location'     => strtoupper( $match[2] ),
                'end_location' => strtoupper( $match[3] ),
            ];
        }

        if ( preg_match( '/^(.+?)\s+(?:in|near)\s+(.+)$/i', $text, $match ) ) {
            return [
                'title'    => trim( $match[1], " \t\n\r\0\x0B,-" ),
                'location' => trim( $match[2], " \t\n\r\0\x0B,-" ),
            ];
        }

        if ( preg_match( '/^(.+?)\s+-\s+(.+)$/', $text, $match ) ) {
            return [
                'title'    => trim( $match[1], " \t\n\r\0\x0B,-" ),
                'location' => trim( $match[2], " \t\n\r\0\x0B,-" ),
            ];
        }

        $words = preg_split( '/\s+/', $text );
        if ( is_array( $words ) && count( $words ) > 1 ) {
            $location = (string) array_pop( $words );
            return [
                'title'    => trim( implode( ' ', $words ) ),
                'location' => $location,
            ];
        }

        return [ 'title' => $text, 'location' => '' ];
    }

    private function infer_type( string $title ): string {
        if ( preg_match( '/\b(flight|airport|airline|boarding|[a-z]{2}\d{1,4})\b/i', $title ) ) {
            return 'flight';
        }
        if ( preg_match( '/\b(hotel|lodging|check-?in|checkout|check-?out|hostel|airbnb)\b/i', $title ) ) {
            return 'lodging';
        }
        if ( preg_match( '/\b(train|rail|bahn|bus)\b/i', $title ) ) {
            return 'train';
        }
        if ( preg_match( '/\b(car|rental)\b/i', $title ) ) {
            return 'car';
        }

        return 'activity';
    }

    private function normalize_spaces( string $text ): string {
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }

    private function title_case( string $text ): string {
        $text = trim( $text );
        if ( '' === $text ) {
            return '';
        }

        if ( preg_match( '/[A-Z]/', $text ) ) {
            return $text;
        }

        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( $text, MB_CASE_TITLE, 'UTF-8' );
        }

        return ucwords( strtolower( $text ) );
    }

}
