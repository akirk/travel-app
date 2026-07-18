<?php

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}

$failures = [];

$test = static function( string $name, callable $callback ) use ( &$failures ): void {
    try {
        $callback();
        echo "PASS $name\n";
    } catch ( Throwable $e ) {
        $failures[] = "$name: " . $e->getMessage();
        echo "FAIL $name\n";
    }
};

$assert = static function( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
};

$finish = static function() use ( &$failures ): void {
    if ( $failures === [] ) {
        return;
    }

    echo "\n";
    foreach ( $failures as $failure ) {
        echo "$failure\n";
    }
    exit( 1 );
};
