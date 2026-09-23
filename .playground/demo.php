<?php
/**
 * Seed the WordPress Playground demo with sample trips.
 *
 * This file is copied into the plugin by demo.json before activation.
 *
 * @package TravelApp
 */

if ( ! taxonomy_exists( 'travel_app_trip' ) || ! post_type_exists( 'travel_app_item' ) ) {
	return;
}

$owner = get_user_by( 'login', 'admin' );
$owner_id = $owner ? $owner->ID : 1;

$demo_anchor_date = '2026-08-19';
$demo_today       = gmdate( 'Y-m-d' );
$demo_day_offset  = (int) ( ( strtotime( $demo_today . ' 00:00:00 UTC' ) - strtotime( $demo_anchor_date . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
$shift_demo_date  = static function ( string $date ) use ( $demo_day_offset ): string {
	if ( '' === $date || 0 === $demo_day_offset ) {
		return $date;
	}

	$date_time = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
	if ( false === $date_time ) {
		return $date;
	}

	return $date_time->modify( sprintf( '%+d days', $demo_day_offset ) )->format( 'Y-m-d' );
};

$trips = [
	[
		'title'    => 'Vienna – Hamburg – Denmark Loop',
		'segments' => [
			[
				'type'     => 'flight',
				'title'    => 'OS 123 Vienna → Hamburg',
				'date'     => '2026-06-12',
				'time'     => '08:15',
				'end_time' => '09:45',
				'location' => 'VIE',
				'end_location' => 'HAM',
				'details'  => "Economy, seat 14C.\nCarry-on only.",
			],
			[
				'type'     => 'lodging',
				'title'    => 'Hotel am Fischmarkt, Hamburg',
				'date'     => '2026-06-12',
				'end_date' => '2026-06-14',
				'time'     => '15:00',
				'end_time' => '11:00',
				'location' => 'Hotel am Fischmarkt, Hamburg',
				'details'  => 'Breakfast included. Free cancellation until June 5.',
			],
			[
				'type'     => 'activity',
				'title'    => 'Speicherstadt walking tour',
				'date'     => '2026-06-13',
				'time'     => '10:00',
				'end_time' => '12:00',
				'location' => 'Speicherstadt, Hamburg',
				'details'  => 'Meet at the Wasserschloss bridge.',
			],
			[
				'type'     => 'car',
				'title'    => 'Rental car: Hamburg Airport → Ribe',
				'date'     => '2026-06-14',
				'time'     => '12:00',
				'location' => 'Hamburg Airport',
				'end_location' => 'Ribe, Denmark',
				'details'  => 'Compact car, unlimited mileage.',
			],
			[
				'type'     => 'lodging',
				'title'    => 'Weis Stue Guesthouse, Ribe',
				'date'     => '2026-06-14',
				'end_date' => '2026-06-16',
				'time'     => '15:00',
				'end_time' => '10:00',
				'location' => 'Weis Stue Guesthouse, Ribe, Denmark',
				'details'  => "Denmark's oldest town. Ask for a room facing the square.",
			],
			[
				'type'     => 'activity',
				'title'    => 'Wadden Sea tidal flat walk',
				'date'     => '2026-06-15',
				'time'     => '14:00',
				'end_time' => '17:00',
				'location' => 'Vester Vedsted, Denmark',
				'details'  => 'Rubber boots provided. Start time depends on the tide.',
			],
			[
				'type'     => 'car',
				'title'    => 'Drive: Ribe → Kiel',
				'date'     => '2026-06-16',
				'time'     => '10:00',
				'location' => 'Ribe, Denmark',
				'end_location' => 'Kiel, Germany',
				'details'  => 'Border crossing near Flensburg, no stop needed.',
			],
			[
				'type'     => 'lodging',
				'title'    => 'Hotel Hafenblick, Kiel',
				'date'     => '2026-06-16',
				'end_date' => '2026-06-17',
				'time'     => '15:00',
				'end_time' => '11:00',
				'location' => 'Hotel Hafenblick, Kiel',
				'details'  => '',
			],
			[
				'type'     => 'train',
				'title'    => 'RE Kiel Hbf → Hamburg Hbf',
				'date'     => '2026-06-17',
				'time'     => '08:20',
				'end_time' => '09:35',
				'location' => 'Kiel Hbf',
				'end_location' => 'Hamburg Hbf',
				'details'  => 'Rental car dropped off at Kiel station beforehand.',
			],
			[
				'type'     => 'flight',
				'title'    => 'OS 128 Hamburg → Vienna',
				'date'     => '2026-06-17',
				'time'     => '18:40',
				'end_time' => '20:05',
				'location' => 'HAM',
				'end_location' => 'VIE',
				'details'  => 'Economy, seat 22A.',
			],
		],
	],
	[
		'title'    => 'Vienna to Tuscany Road Trip',
		'segments' => [
			[
				'type'     => 'car',
				'title'    => 'Rental car: Vienna → Verona',
				'date'     => '2026-09-05',
				'time'     => '08:00',
				'location' => 'Vienna',
				'end_location' => 'Verona, Italy',
				'details'  => 'Long first leg — split the drive with a lunch stop at Villach.',
			],
			[
				'type'     => 'lodging',
				'title'    => 'Hotel Milano, Verona',
				'date'     => '2026-09-05',
				'end_date' => '2026-09-06',
				'time'     => '19:00',
				'end_time' => '10:00',
				'location' => 'Hotel Milano, Verona',
				'details'  => 'One-night stop to break up the drive.',
			],
			[
				'type'     => 'car',
				'title'    => 'Drive: Verona → Florence',
				'date'     => '2026-09-06',
				'time'     => '11:00',
				'location' => 'Verona, Italy',
				'end_location' => 'Florence, Italy',
				'details'  => 'Scenic route via the A1 past Bologna.',
			],
			[
				'type'     => 'lodging',
				'title'    => 'Agriturismo Le Colline, Chianti',
				'date'     => '2026-09-06',
				'end_date' => '2026-09-10',
				'time'     => '16:00',
				'end_time' => '10:00',
				'location' => 'Agriturismo Le Colline, Chianti',
				'details'  => 'Base for exploring Florence and the Chianti countryside.',
			],
			[
				'type'     => 'activity',
				'title'    => 'Florence: Duomo & Uffizi',
				'date'     => '2026-09-07',
				'time'     => '09:30',
				'end_time' => '13:00',
				'location' => 'Florence, Italy',
				'details'  => 'Book Uffizi tickets ahead to skip the line.',
			],
			[
				'type'     => 'train',
				'title'    => 'Train: Florence → Siena',
				'date'     => '2026-09-08',
				'time'     => '09:15',
				'end_time' => '10:30',
				'location' => 'Firenze Santa Maria Novella',
				'end_location' => 'Siena',
				'details'  => "Day trip, no car needed for Siena's old town.",
			],
			[
				'type'     => 'activity',
				'title'    => 'Wine tasting in Chianti',
				'date'     => '2026-09-09',
				'time'     => '15:00',
				'end_time' => '18:00',
				'location' => 'Chianti, Tuscany',
				'details'  => 'Tasting and cellar tour at a local vineyard.',
			],
			[
				'type'     => 'car',
				'title'    => 'Drive: Chianti → Pisa',
				'date'     => '2026-09-10',
				'time'     => '11:00',
				'location' => 'Chianti, Tuscany',
				'end_location' => 'Pisa, Italy',
				'details'  => 'Stop at the Leaning Tower before returning the car.',
			],
			[
				'type'     => 'flight',
				'title'    => 'AZ 456 Pisa → Vienna',
				'date'     => '2026-09-10',
				'time'     => '19:20',
				'end_time' => '20:45',
				'location' => 'PSA',
				'end_location' => 'VIE',
				'details'  => 'Economy, seat 17C.',
			],
		],
	],
	[
		'title'    => 'New York Business Trip',
		'segments' => [
			[
				'type'     => 'flight',
				'title'    => 'OS 89 Vienna → New York JFK',
				'date'     => '2026-11-03',
				'time'     => '11:30',
				'end_time' => '14:55',
				'location' => 'VIE',
				'end_location' => 'JFK',
				'details'  => 'Direct flight. Economy, seat 31A.',
			],
			[
				'type'     => 'lodging',
				'title'    => 'The Midtown Hotel, Manhattan',
				'date'     => '2026-11-03',
				'end_date' => '2026-11-06',
				'time'     => '15:00',
				'end_time' => '11:00',
				'location' => 'The Midtown Hotel, Manhattan',
				'details'  => 'Booked through the company travel portal.',
			],
			[
				'type'     => 'activity',
				'title'    => 'Client meeting at the Midtown office',
				'date'     => '2026-11-04',
				'time'     => '09:00',
				'end_time' => '17:00',
				'location' => 'Midtown Manhattan',
				'details'  => 'Bring the signed contract draft.',
			],
			[
				'type'     => 'flight',
				'title'    => 'OS 90 New York JFK → Vienna',
				'date'     => '2026-11-06',
				'end_date' => '2026-11-07',
				'time'     => '18:15',
				'end_time' => '08:10',
				'location' => 'JFK',
				'end_location' => 'VIE',
				'details'  => 'Direct flight, overnight. Economy, seat 28C.',
			],
		],
	],
];

foreach ( $trips as $trip_definition ) {
	$trip = wp_insert_term( $trip_definition['title'], 'travel_app_trip', [
		'slug' => sanitize_title( $trip_definition['title'] . '-' . $owner_id . '-demo' ),
	] );

	if ( is_wp_error( $trip ) ) {
		continue;
	}

	$trip_id = (int) $trip['term_id'];
	update_term_meta( $trip_id, '_travel_app_user_id', $owner_id );
	update_term_meta( $trip_id, '_travel_app_created_by_user_id', $owner_id );

	$dates = [];
	foreach ( $trip_definition['segments'] as $segment ) {
		$segment = wp_parse_args( $segment, [
			'end_date'     => '',
			'end_time'     => '',
			'location'     => '',
			'end_location' => '',
			'details'      => '',
		] );
		$segment['date']     = $shift_demo_date( $segment['date'] );
		$segment['end_date'] = $shift_demo_date( $segment['end_date'] );

		$item_id = wp_insert_post( [
			'post_type'    => 'travel_app_item',
			'post_status'  => 'private',
			'post_author'  => $owner_id,
			'post_title'   => $segment['title'],
			'post_content' => $segment['details'],
		], true );

		if ( is_wp_error( $item_id ) ) {
			continue;
		}

		wp_set_object_terms( $item_id, [ $trip_id ], 'travel_app_trip', false );

		update_post_meta( $item_id, '_travel_app_type', $segment['type'] );
		update_post_meta( $item_id, '_travel_app_date', $segment['date'] );
		update_post_meta( $item_id, '_travel_app_end_date', $segment['end_date'] );
		update_post_meta( $item_id, '_travel_app_time', $segment['time'] );
		update_post_meta( $item_id, '_travel_app_end_time', $segment['end_time'] );
		update_post_meta( $item_id, '_travel_app_starts_at_utc', '' );
		update_post_meta( $item_id, '_travel_app_ends_at_utc', '' );
		update_post_meta( $item_id, '_travel_app_timezone', '' );
		update_post_meta( $item_id, '_travel_app_location', $segment['location'] );
		update_post_meta( $item_id, '_travel_app_end_location', $segment['end_location'] );
		update_post_meta( $item_id, '_travel_app_url', '' );
		update_post_meta( $item_id, '_travel_app_sort', trim( $segment['date'] . ' ' . $segment['time'] ) );
		update_post_meta( $item_id, '_travel_app_owner_user_id', $owner_id );
		update_post_meta( $item_id, '_travel_app_created_by_user_id', $owner_id );

		$dates[] = $segment['date'];
		if ( '' !== $segment['end_date'] ) {
			$dates[] = $segment['end_date'];
		}
	}

	sort( $dates );
	update_term_meta( $trip_id, '_travel_app_starts_at', $dates[0] ?? '' );
	update_term_meta( $trip_id, '_travel_app_ends_at', $dates ? end( $dates ) : '' );
}
