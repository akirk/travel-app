<?php

use PHPUnit\Framework\TestCase;
use TravelApp\LodgingCoverage;

final class LodgingCoverageTest extends TestCase {
    public function test_reports_no_missing_nights_when_lodging_covers_trip(): void {
        $coverage = LodgingCoverage::analyze(
            [
                'starts_at' => '2026-07-19',
                'ends_at'   => '2026-07-22',
            ],
            [
                [
                    'type'     => 'lodging',
                    'title'    => 'Hotel',
                    'date'     => '2026-07-19',
                    'end_date' => '2026-07-22',
                    'location' => 'Hamburg',
                ],
            ]
        );

        self::assertSame( [ '2026-07-19', '2026-07-20', '2026-07-21' ], $coverage['required_nights'] );
        self::assertSame( [], $coverage['missing_nights'] );
        self::assertSame( [], $coverage['missing_ranges'] );
        self::assertSame( [], $coverage['missing_details'] );
        self::assertSame(
            [
                [
                    'date'       => '2026-07-19',
                    'end_date'   => '2026-07-20',
                    'item_id'    => 0,
                    'item_title' => 'Hotel',
                    'item_type'  => 'lodging',
                    'location'   => 'Hamburg',
                ],
                [
                    'date'       => '2026-07-20',
                    'end_date'   => '2026-07-21',
                    'item_id'    => 0,
                    'item_title' => 'Hotel',
                    'item_type'  => 'lodging',
                    'location'   => 'Hamburg',
                ],
                [
                    'date'       => '2026-07-21',
                    'end_date'   => '2026-07-22',
                    'item_id'    => 0,
                    'item_title' => 'Hotel',
                    'item_type'  => 'lodging',
                    'location'   => 'Hamburg',
                ],
            ],
            $coverage['covered_details']
        );
    }

    public function test_reports_missing_nights_with_next_morning_and_inferred_location(): void {
        $coverage = LodgingCoverage::analyze(
            [
                'starts_at' => '2026-07-19',
                'ends_at'   => '2026-07-23',
            ],
            [
                [
                    'type'         => 'train',
                    'date'         => '2026-07-19',
                    'end_date'     => '2026-07-19',
                    'location'     => 'Berlin',
                    'end_location' => 'Hamburg',
                ],
                [
                    'type'     => 'lodging',
                    'title'    => 'Hotel',
                    'date'     => '2026-07-21',
                    'end_date' => '2026-07-23',
                    'location' => 'Hamburg',
                ],
            ]
        );

        self::assertSame( [ '2026-07-19', '2026-07-20' ], $coverage['missing_nights'] );
        self::assertSame(
            [
                [
                    'start' => '2026-07-19',
                    'end'   => '2026-07-21',
                ],
            ],
            $coverage['missing_ranges']
        );
        self::assertSame(
            [
                [
                    'date'     => '2026-07-19',
                    'end_date' => '2026-07-20',
                    'location' => 'Hamburg',
                ],
                [
                    'date'     => '2026-07-20',
                    'end_date' => '2026-07-21',
                    'location' => 'Hamburg',
                ],
            ],
            $coverage['missing_details']
        );
    }

    public function test_multiday_travel_covers_the_travel_night(): void {
        $coverage = LodgingCoverage::analyze(
            [
                'starts_at' => '2026-07-19',
                'ends_at'   => '2026-07-22',
            ],
            [
                [
                    'type'         => 'train',
                    'title'        => 'Train to Hamburg',
                    'date'         => '2026-07-19',
                    'end_date'     => '2026-07-20',
                    'location'     => 'Vienna',
                    'end_location' => 'Hamburg',
                ],
                [
                    'type'     => 'lodging',
                    'title'    => 'Hotel',
                    'date'     => '2026-07-20',
                    'end_date' => '2026-07-22',
                    'location' => 'Hamburg',
                ],
            ]
        );

        self::assertSame( [], $coverage['missing_nights'] );
        self::assertSame(
            [
                [
                    'date'       => '2026-07-19',
                    'end_date'   => '2026-07-20',
                    'item_id'    => 0,
                    'item_title' => 'Train to Hamburg',
                    'item_type'  => 'train',
                    'location'   => 'Hamburg',
                ],
                [
                    'date'       => '2026-07-20',
                    'end_date'   => '2026-07-21',
                    'item_id'    => 0,
                    'item_title' => 'Hotel',
                    'item_type'  => 'lodging',
                    'location'   => 'Hamburg',
                ],
                [
                    'date'       => '2026-07-21',
                    'end_date'   => '2026-07-22',
                    'item_id'    => 0,
                    'item_title' => 'Hotel',
                    'item_type'  => 'lodging',
                    'location'   => 'Hamburg',
                ],
            ],
            $coverage['covered_details']
        );
    }
}
