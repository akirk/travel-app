<?php

use PHPUnit\Framework\TestCase;

final class SharedTimelineUrlTest extends TestCase {
    public function test_shared_timeline_url_uses_the_app_path(): void {
        $app_source = file_get_contents( dirname( __DIR__ ) . '/src/App.php' );

        $this->assertIsString( $app_source );
        $this->assertStringContainsString(
            "home_url( '/' . \$this->get_url_path() . '/share/' . \$trip_id . '/' )",
            $app_source
        );
        $this->assertStringContainsString( "'token' => \$token", $app_source );
        $this->assertStringContainsString( "private const SHARE_PAGE_VERSION = '3'", $app_source );
        $this->assertStringContainsString( "'v'     => self::SHARE_PAGE_VERSION", $app_source );
        $this->assertStringNotContainsString( 'DONOTCACHEPAGE', $app_source );
        $this->assertStringContainsString( 'nocache_headers();', $app_source );
        $this->assertStringNotContainsString( "get_query_arg_absint( 'travel_app_share' )", $app_source );
        $this->assertStringNotContainsString( "get_query_arg_text( 'travel_app_token' );\n\n        return '' !== \$token", $app_source );
        $this->assertStringContainsString( "#\\Ashare/([0-9]+)/?\\z#", $app_source );

        $template = file_get_contents( dirname( __DIR__ ) . '/templates/trip.php' );
        $this->assertIsString( $template );
        $this->assertStringContainsString( "\$is_shared_timeline ? 'global' : ''", $template );
        $this->assertStringContainsString( 'show_admin_bar( false )', $template );
        $this->assertStringContainsString( "remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 )", $template );
        $this->assertStringContainsString( "wp_dequeue_style( 'admin-bar' )", $template );
        $this->assertStringContainsString( "add_action( 'wp_head', static function(): void", $template );
        $this->assertStringContainsString( '! $is_static_download && ! $is_shared_timeline', $template );
    }
}
