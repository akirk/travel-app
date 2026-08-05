<?php

use PHPUnit\Framework\TestCase;
use TravelApp\ItineraryItem;
use TravelApp\Trip;

final class DomainSchemaTest extends TestCase {
    public function test_trip_schema_matches_public_properties(): void {
        $schema_fields = array_keys( Trip::schema()['properties'] );
        $property_fields = $this->public_property_names( Trip::class );
        $computed_fields = [ 'is_active', 'segment_count', 'segments', 'url', 'share_urls', 'missing_fields' ];

        $this->assert_same_fields( array_merge( $property_fields, $computed_fields ), $schema_fields );
    }

    public function test_trip_active_data_matches_date_range(): void {
        self::assertTrue( Trip::is_active_data( [
            'starts_at' => '2026-08-01',
            'ends_at'   => '2026-08-05',
        ], '2026-08-05' ) );

        self::assertTrue( Trip::is_active_data( [
            'starts_at' => '2026-08-01',
            'ends_at'   => '',
        ], '2026-08-05' ) );

        self::assertFalse( Trip::is_active_data( [
            'starts_at' => '2026-08-06',
            'ends_at'   => '2026-08-10',
        ], '2026-08-05' ) );

        self::assertFalse( Trip::is_active_data( [
            'starts_at' => '',
            'ends_at'   => '2026-08-05',
        ], '2026-08-05' ) );
    }

    public function test_itinerary_item_schema_matches_public_properties(): void {
        $schema_fields = array_keys( ItineraryItem::schema()['properties'] );
        $property_fields = $this->public_property_names( ItineraryItem::class );

        $this->assert_same_fields( $property_fields, $schema_fields );
    }

    public function test_itinerary_item_input_schema_uses_editable_public_properties(): void {
        $input_schema_fields = array_keys( ItineraryItem::input_schema()['properties'] );
        $readonly_fields = [ 'id', 'app_url', 'url_preview', 'url_preview_debug', 'attachments' ];
        $property_fields = array_values( array_diff( $this->public_property_names( ItineraryItem::class ), $readonly_fields ) );

        $this->assert_same_fields( $property_fields, $input_schema_fields );
    }

    private function assert_same_fields( array $expected, array $actual ): void {
        sort( $expected );
        sort( $actual );

        self::assertSame( $expected, $actual );
    }

    private function public_property_names( string $class_name ): array {
        $reflection = new ReflectionClass( $class_name );
        $properties = array_filter( $reflection->getProperties( ReflectionProperty::IS_PUBLIC ), static function( ReflectionProperty $property ): bool {
            return ! $property->isStatic();
        } );

        return array_values( array_map( static function( ReflectionProperty $property ): string {
            return $property->getName();
        }, $properties ) );
    }
}
