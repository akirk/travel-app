<?php

namespace TravelApp\Parser;

class IcsParser {
    public function supports( string $text ): bool {
        return false !== stripos( $text, 'BEGIN:VCALENDAR' ) || false !== stripos( $text, 'BEGIN:VEVENT' );
    }

    public function parse( string $text ): array {
        $calendar_title = $this->extract_calendar_title( $text );
        $events = $this->extract_events( $text );
        $segments = [];
        $overview_title = '';

        foreach ( $events as $event ) {
            $summary = $event['SUMMARY'] ?? __( 'Calendar event', 'travel-app' );
            $description = $event['DESCRIPTION'] ?? '';
            $location = $event['LOCATION'] ?? '';
            if ( $this->is_tripit_overview_event( $event, $summary, $description, $calendar_title ) ) {
                $overview_title = trim( $summary );
                continue;
            }

            $locations = $this->extract_tripit_locations( $description );
            $details = $this->clean_description( $description, $summary, $location );
            $start = $this->parse_datetime( $event['_DTSTART_RAW'] ?? '' );
            $end = $this->parse_datetime( $event['_DTEND_RAW'] ?? '' );
            $local_times = $this->extract_tripit_local_times( $description );
            if ( '' !== $local_times['start_time'] ) {
                $start['time'] = $local_times['start_time'];
            }
            if ( '' !== $local_times['end_time'] ) {
                $end['time'] = $local_times['end_time'];
            }

            $segments[] = [
                'type'      => $this->infer_segment_type( $summary . ' ' . $description . ' ' . $location ),
                'title'     => $summary,
                'date'      => $start['date'],
                'end_date'  => $end['date'],
                'time'      => $start['time'],
                'end_time'  => $end['time'],
                'starts_at_utc' => $start['utc'],
                'ends_at_utc' => $end['utc'],
                'timezone'  => $local_times['timezone'],
                'location'  => $locations['location'] ?: $location,
                'end_location' => $locations['end_location'],
                'details'   => $details,
                '_sort_key' => $start['sort_key'],
                '_ends_at'  => $end['date'] ?: $start['date'],
            ];
        }

        usort( $segments, static function( array $a, array $b ): int {
            return strcmp( (string) ( $a['_sort_key'] ?? '' ), (string) ( $b['_sort_key'] ?? '' ) );
        } );

        $segments = $this->merge_lodging_check_events( $segments );

        $starts_at = '';
        $ends_at = '';
        foreach ( $segments as &$segment ) {
            if ( '' === $starts_at && ! empty( $segment['date'] ) ) {
                $starts_at = $segment['date'];
            }
            if ( ! empty( $segment['_ends_at'] ) ) {
                $ends_at = $segment['_ends_at'];
            } elseif ( ! empty( $segment['date'] ) ) {
                $ends_at = $segment['date'];
            }

            unset( $segment['_sort_key'], $segment['_ends_at'] );
        }
        unset( $segment );

        $trip_title = $overview_title ?: $calendar_title ?: $this->build_title( $segments );

        return [
            'title'       => $trip_title,
            'starts_at'   => $starts_at,
            'ends_at'     => $ends_at,
            'segments'    => $segments,
            'parser'      => 'ics',
        ];
    }

    private function unfold_lines( string $text ): array {
        $lines = preg_split( '/\r\n|\r|\n/', $text );
        $unfolded = [];

        foreach ( $lines as $line ) {
            $line = rtrim( (string) $line, "\r\n" );
            if ( '' !== $line && preg_match( '/^[ \t]/', $line ) && ! empty( $unfolded ) ) {
                $unfolded[ count( $unfolded ) - 1 ] .= substr( $line, 1 );
                continue;
            }

            $unfolded[] = $line;
        }

        return $unfolded;
    }

    private function extract_calendar_title( string $text ): string {
        foreach ( $this->unfold_lines( $text ) as $line ) {
            if ( 0 !== stripos( $line, 'X-WR-CALNAME:' ) ) {
                continue;
            }

            $title = $this->decode_text( substr( $line, strlen( 'X-WR-CALNAME:' ) ) );
            if ( preg_match( '/\(\s*TripIt\s+-\s*([^)]+)\s*\)\s*$/i', $title, $match ) ) {
                return trim( $match[1] );
            }

            $title = preg_replace( '/\s*\(TripIt\s+-\s*[^)]+\)\s*$/i', '', $title );
            return trim( (string) $title );
        }

        return '';
    }

    private function extract_events( string $text ): array {
        $events = [];
        $event = null;

        foreach ( $this->unfold_lines( $text ) as $line ) {
            if ( 0 === strcasecmp( $line, 'BEGIN:VEVENT' ) ) {
                $event = [];
                continue;
            }

            if ( 0 === strcasecmp( $line, 'END:VEVENT' ) ) {
                if ( is_array( $event ) ) {
                    $events[] = $event;
                }
                $event = null;
                continue;
            }

            if ( null === $event || false === strpos( $line, ':' ) ) {
                continue;
            }

            list( $name, $value ) = explode( ':', $line, 2 );
            $property = strtoupper( strtok( $name, ';' ) );

            if ( in_array( $property, [ 'SUMMARY', 'DESCRIPTION', 'LOCATION', 'UID' ], true ) ) {
                $event[ $property ] = $this->decode_text( $value );
            } elseif ( 'DTSTART' === $property ) {
                $event['_DTSTART_RAW'] = $value;
            } elseif ( 'DTEND' === $property ) {
                $event['_DTEND_RAW'] = $value;
            }
        }

        return $events;
    }

    private function is_tripit_overview_event( array $event, string $summary, string $description, string $calendar_title ): bool {
        $uid = (string) ( $event['UID'] ?? '' );
        $start = (string) ( $event['_DTSTART_RAW'] ?? '' );
        $end = (string) ( $event['_DTEND_RAW'] ?? '' );

        if ( false === stripos( $uid, '@tripit.com' ) ) {
            return false;
        }

        if ( ! preg_match( '/^\d{8}$/', $start ) || ! preg_match( '/^\d{8}$/', $end ) ) {
            return false;
        }

        return ( '' !== $calendar_title && 0 === strcasecmp( trim( $summary ), $calendar_title ) )
            || false !== stripos( $description, ' is in ' );
    }

    private function decode_text( string $value ): string {
        $value = str_replace( [ '\\n', '\\N' ], "\n", $value );
        $value = str_replace( [ '\\,', '\\;', '\\\\' ], [ ',', ';', '\\' ], $value );
        $value = preg_replace( '#https?://\S+#i', '', $value );
        $value = preg_replace( '/\bView and\/or edit details in TripIt\s*:?\s*/i', '', $value );
        $value = preg_replace( '/\bTripIt\s+-\s+organize your travel\b.*$/im', '', $value );
        $lines = array_map( 'trim', preg_split( '/\R/', $value ) );
        $lines = array_filter( $lines, static function( string $line ): bool {
            return '' !== $line && ! preg_match( '#^(?:webcal://|www\.|ip/show/id/)#i', $line );
        } );
        $lines = array_map( static function( string $line ): string {
            return (string) preg_replace( '/[ \t]+/', ' ', $line );
        }, $lines );

        return implode( "\n", $lines );
    }

    private function clean_description( string $description, string $summary, string $location ): string {
        $lines = array_map( 'trim', preg_split( '/\R/', $description ) );
        $summary = trim( $summary );
        $location = trim( $location );

        $lines = array_values( array_filter( $lines, static function( string $line ) use ( $summary, $location ): bool {
            if ( '' === $line ) {
                return false;
            }
            if ( $summary !== '' && false !== stripos( $line, $summary ) ) {
                return false;
            }
            if ( $location !== '' && $line === $location ) {
                return false;
            }
            if ( preg_match( '/^\d{1,2}:\d{2}\s+[A-Z]{2,5}$/', $line ) ) {
                return false;
            }
            if ( preg_match( '/^\d{1,2}:\d{2}\s+to\s+\d{1,2}:\d{2}$/i', $line ) ) {
                return false;
            }
            if ( preg_match( '/^(Sun|Mon|Tue|Wed|Thu|Fri|Sat),\s+/i', $line ) ) {
                return false;
            }
            if ( preg_match( '/^Until\s+/i', $line ) ) {
                return false;
            }
            if ( preg_match( '/^\[(?:Lodging|Hotel)\]\s+(?:Arrive|Depart)\b/i', $line ) ) {
                return false;
            }
            if ( preg_match( '/^Check-?(?:In|Out)\s*:\s*\d{1,2}:\d{2}\b/i', $line ) ) {
                return false;
            }
            if ( preg_match( '/^(?:Depart|Arrive)\s+/i', $line ) ) {
                return false;
            }

            return true;
        } ) );

        return implode( "\n", $lines );
    }

    private function extract_tripit_locations( string $description ): array {
        $locations = [
            'location'     => '',
            'end_location' => '',
        ];

        foreach ( preg_split( '/\R/', $description ) as $line ) {
            $line = trim( (string) $line );

            if ( '' === $locations['location'] && preg_match( '/^Depart\s+(.+)$/i', $line, $match ) ) {
                $locations['location'] = trim( $match[1] );
                continue;
            }

            if ( '' === $locations['end_location'] && preg_match( '/^Arrive\s+(.+)$/i', $line, $match ) ) {
                $locations['end_location'] = trim( $match[1] );
            }
        }

        return $locations;
    }

    private function extract_tripit_local_times( string $description ): array {
        $times = [
            'start_time' => '',
            'end_time'   => '',
            'timezone'   => '',
        ];

        foreach ( preg_split( '/\R/', $description ) as $line ) {
            $line = trim( (string) $line );
            if ( preg_match( '/^(\d{1,2}):(\d{2})\s+to\s+(\d{1,2}):(\d{2})$/i', $line, $match ) ) {
                $times['start_time'] = str_pad( $match[1], 2, '0', STR_PAD_LEFT ) . ':' . $match[2];
                $times['end_time'] = str_pad( $match[3], 2, '0', STR_PAD_LEFT ) . ':' . $match[4];
                continue;
            }
            if ( preg_match( '/^(\d{1,2}):(\d{2})\s+[A-Z]{2,5}$/', $line, $match ) ) {
                $time = str_pad( $match[1], 2, '0', STR_PAD_LEFT ) . ':' . $match[2];
                if ( preg_match( '/\s+([A-Z]{2,5})$/', $line, $timezone_match ) ) {
                    $times['timezone'] = $timezone_match[1];
                }
                if ( '' === $times['start_time'] ) {
                    $times['start_time'] = $time;
                } elseif ( '' === $times['end_time'] ) {
                    $times['end_time'] = $time;
                }
            }
        }

        return $times;
    }

    private function parse_datetime( string $value ): array {
        $value = trim( $value );
        $is_utc = 'Z' === substr( $value, -1 );
        $value = rtrim( $value, 'Z' );
        $date = '';
        $time = '';
        $sort_key = $value;
        $utc = '';

        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})?)?$/', $value, $match ) ) {
            $date = $match[1] . '-' . $match[2] . '-' . $match[3];
            if ( ! empty( $match[4] ) ) {
                $time = $match[4] . ':' . $match[5];
                if ( $is_utc ) {
                    $utc = $date . 'T' . $time . ':' . ( $match[6] ?? '00' ) . 'Z';
                }
            }
            $sort_key = $date . ' ' . $time;
        }

        return [
            'date'     => $date,
            'time'     => $time,
            'utc'      => $utc,
            'sort_key' => $sort_key,
        ];
    }

    private function build_title( array $segments ): string {
        foreach ( $segments as $segment ) {
            $title = (string) ( $segment['title'] ?? '' );
            if ( '' !== $title && ! preg_match( '/^(check-in|check-out):/i', $title ) ) {
                return $title;
            }
        }

        return __( 'Imported Calendar Itinerary', 'travel-app' );
    }

    private function merge_lodging_check_events( array $segments ): array {
        $merged = [];
        $pending_checkins = [];

        foreach ( $segments as $segment ) {
            if ( 'lodging' !== ( $segment['type'] ?? '' ) ) {
                $merged[] = $segment;
                continue;
            }

            $title = (string) ( $segment['title'] ?? '' );
            $lodging_name = $this->normalize_lodging_name( $title );

            if ( preg_match( '/^check-in\s*:/i', $title ) ) {
                $segment['title'] = $lodging_name;
                $pending_checkins[ $lodging_name ] = $segment;
                continue;
            }

            if ( preg_match( '/^check-out\s*:/i', $title ) && isset( $pending_checkins[ $lodging_name ] ) ) {
                $checkin = $pending_checkins[ $lodging_name ];
                unset( $pending_checkins[ $lodging_name ] );

                $checkin['end_date'] = (string) ( $segment['date'] ?? '' );
                $checkin['end_time'] = (string) ( $segment['time'] ?? '' );
                $checkin['ends_at_utc'] = (string) ( $segment['starts_at_utc'] ?? ( $segment['ends_at_utc'] ?? '' ) );
                $checkin['timezone'] = (string) ( $segment['timezone'] ?? ( $checkin['timezone'] ?? '' ) );
                $checkin['_ends_at'] = (string) ( $segment['date'] ?? ( $checkin['_ends_at'] ?? '' ) );

                $merged[] = $checkin;
                continue;
            }

            $merged[] = $segment;
        }

        foreach ( $pending_checkins as $checkin ) {
            $merged[] = $checkin;
        }

        usort( $merged, static function( array $a, array $b ): int {
            return strcmp( (string) ( $a['_sort_key'] ?? '' ), (string) ( $b['_sort_key'] ?? '' ) );
        } );

        return $merged;
    }

    private function normalize_lodging_name( string $title ): string {
        $title = preg_replace( '/^check-(?:in|out)\s*:\s*/i', '', $title );
        $title = trim( (string) $title );

        return '' !== $title ? $title : __( 'Lodging', 'travel-app' );
    }

    private function first_non_empty_segment_value( array $segments, string $key ): string {
        foreach ( $segments as $segment ) {
            if ( ! empty( $segment[ $key ] ) ) {
                return (string) $segment[ $key ];
            }
        }

        return '';
    }

    private function infer_segment_type( string $text ): string {
        if ( preg_match( '/\b(lodging|hotel|check-in|checkout|check-out|airbnb|boardinghouse)\b/i', $text ) ) {
            return 'lodging';
        }
        if ( preg_match( '/\b(car|rental|mietwagen|alamo|hertz|avis|sixt)\b/i', $text ) ) {
            return 'car';
        }
        if ( preg_match( '/\b(flight|airport|airline|boarding pass)\b/i', $text ) ) {
            return 'flight';
        }
        if ( preg_match( '/\b(train|rail)\b/i', $text ) ) {
            return 'train';
        }
        if ( preg_match( '/\b(activity|tour|museum|attraction|event)\b/i', $text ) ) {
            return 'activity';
        }
        return 'other';
    }
}
