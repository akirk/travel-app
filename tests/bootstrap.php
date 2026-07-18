<?php

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;

        public function __construct( string $code = '', string $message = '' ) {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'wp_trim_words' ) ) {
    function wp_trim_words( string $text, int $num_words = 55, string $more = null ): string {
        $words = preg_split( '/\s+/', trim( $text ) );
        if ( ! is_array( $words ) ) {
            return '';
        }

        return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( count( $words ) > $num_words ? (string) $more : '' );
    }
}
