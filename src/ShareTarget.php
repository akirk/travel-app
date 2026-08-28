<?php
namespace Traveler;

/**
 * Pure helpers for the Web Share Target request, kept free of WordPress so
 * they can be unit tested.
 */
class ShareTarget {
    /**
     * Joins the shared title, text and URL with file contents, skipping
     * blanks and duplicates (Android often sends the URL as text too).
     *
     * @param array<string,string> $fields   Shared title/text/url values.
     * @param string[]             $contents Contents of accepted files.
     */
    public static function build_text( array $fields, array $contents = [] ): string {
        $parts = [];
        foreach ( [ 'title', 'text', 'url' ] as $field ) {
            $value = trim( (string) ( $fields[ $field ] ?? '' ) );
            if ( '' !== $value && ! in_array( $value, $parts, true ) ) {
                $parts[] = $value;
            }
        }

        foreach ( $contents as $content ) {
            $content = trim( (string) $content );
            if ( '' !== $content ) {
                $parts[] = $content;
            }
        }

        return implode( "\n\n", $parts );
    }

    /**
     * Normalizes one $_FILES entry, whether it holds a single upload or the
     * per-field arrays PHP produces for `files[]`, into a list of file arrays.
     */
    public static function normalize_files( $files ): array {
        if ( ! is_array( $files ) || ! isset( $files['name'] ) ) {
            return [];
        }

        if ( ! is_array( $files['name'] ) ) {
            return [ $files ];
        }

        $list = [];
        foreach ( array_keys( $files['name'] ) as $index ) {
            $list[] = [
                'name'     => $files['name'][ $index ] ?? '',
                'type'     => $files['type'][ $index ] ?? '',
                'tmp_name' => $files['tmp_name'][ $index ] ?? '',
                'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][ $index ] ?? 0,
            ];
        }

        return $list;
    }

    /**
     * Only calendar and plain text files are read; PDFs and other binaries
     * are rejected.
     */
    public static function is_text_file( array $file ): bool {
        $name = strtolower( (string) ( $file['name'] ?? '' ) );
        $type = strtolower( trim( (string) strtok( (string) ( $file['type'] ?? '' ), ';' ) ) );
        $extension = pathinfo( $name, PATHINFO_EXTENSION );

        return in_array( $extension, [ 'ics', 'txt', 'ical', 'ifb', 'icalendar' ], true )
            || in_array( $type, [ 'text/calendar', 'text/plain' ], true );
    }
}
