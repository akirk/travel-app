<?php
use TravelApp\App;

global $wp_app_route;

$travel_app = App::get_instance();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$trip       = $travel_app->get_user_trip( $trip_id );

if ( ! $trip ) {
    status_header( 404 );
}

$trip_data = $trip ? $travel_app->format_trip_for_output( $trip ) : null;
$segments  = $trip_data['segments'] ?? [];
$route_locations = [];
$route_entries = [];

foreach ( $segments as $segment ) {
    foreach ( [ 'location', 'end_location' ] as $location_key ) {
        $location = trim( (string) ( $segment[ $location_key ] ?? '' ) );

        if ( '' === $location ) {
            continue;
        }

        if ( empty( $route_locations ) || end( $route_locations ) !== $location ) {
            $route_locations[] = $location;
            $route_entries[] = [
                'location' => $location,
                'kind'     => 'end_location' === $location_key ? __( 'End location', 'travel-app' ) : __( 'Location', 'travel-app' ),
                'title'    => (string) ( $segment['title'] ?: __( 'Untitled item', 'travel-app' ) ),
                'type'     => (string) ( $segment['type'] ?? '' ),
                'date'     => (string) ( $segment['date'] ?? '' ),
                'time'     => (string) ( $segment['time'] ?? '' ),
                'details'  => (string) ( $segment['details'] ?? '' ),
                'url'      => home_url( '/travel-app/trip/' . $trip_id . '/item/' . (int) ( $segment['id'] ?? 0 ) . '/' ),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( $trip_data ? sprintf( __( '%s Route Map', 'travel-app' ), $trip_data['title'] ) : __( 'Route Map', 'travel-app' ) ); ?></title>
    <?php remove_action( 'wp_head', '_wp_render_title_tag', 1 ); ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php wp_app_head(); ?>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.5;
            background: var(--wp-app-color-background);
            color: var(--wp-app-color-text);
        }
        main { max-width: 1100px; margin: 0 auto; padding: 32px 18px 56px; }
        a { color: var(--wp-app-color-link); }
        h1, p { margin-top: 0; }
        h1 {
            margin-bottom: 6px;
            font-size: clamp(1.65rem, 4vw, 2.7rem);
            line-height: 1.08;
            letter-spacing: 0;
        }
        .map-header {
            padding: 0 0 18px;
            border-bottom: 1px solid var(--wp-app-color-border);
        }
        .topbar { margin-bottom: 16px; }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            margin: 0;
            color: var(--wp-app-color-muted);
        }
        .map-shell {
            position: relative;
            min-height: 620px;
            margin-top: 18px;
            overflow: hidden;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
        }
        #route-map {
            position: absolute;
            inset: 0;
            background: #d9e4dd;
        }
        .map-status {
            position: absolute;
            z-index: 500;
            left: 16px;
            top: 16px;
            max-width: min(520px, calc(100% - 32px));
            box-sizing: border-box;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 10px 12px;
            background: var(--wp-app-color-surface);
            color: var(--wp-app-color-text);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        .map-status:empty { display: none; }
        .route-popup {
            display: grid;
            gap: 4px;
            min-width: 220px;
            max-width: 300px;
            color: #1f2933;
        }
        .route-popup-title {
            font-weight: 800;
            line-height: 1.25;
        }
        .route-popup-meta {
            color: #52606d;
            font-size: 0.88rem;
        }
        .route-popup-location {
            font-weight: 650;
        }
        .route-popup-details {
            margin-top: 4px;
            color: #323f4b;
        }
        .route-popup-title a { font-weight: 800; }
        .route-marker {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border: 2px solid #fff;
            border-radius: 50%;
            background: #0b6bcb;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.28);
        }
        @media (max-width: 680px) {
            .map-shell { min-height: 520px; }
        }
    </style>
</head>
<body>
    <?php wp_app_body_open(); ?>

    <main>
        <header class="map-header">
            <div class="topbar">
                <a href="<?php echo esc_url( home_url( '/travel-app/trip/' . $trip_id . '/' ) ); ?>"><?php esc_html_e( 'Back to Travel Plan', 'travel-app' ); ?></a>
            </div>

            <?php if ( ! $trip_data ) : ?>
                <h1><?php esc_html_e( 'Travel plan not found', 'travel-app' ); ?></h1>
                <p class="meta"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'travel-app' ); ?></p>
            <?php else : ?>
                <h1><?php echo esc_html( sprintf( __( '%s Route Map', 'travel-app' ), $trip_data['title'] ) ); ?></h1>
                <p class="meta">
                    <span><?php echo esc_html( sprintf( _n( '%d waypoint', '%d waypoints', count( $route_entries ), 'travel-app' ), count( $route_entries ) ) ); ?></span>
                    <span><?php esc_html_e( 'Straight lines between itinerary locations', 'travel-app' ); ?></span>
                </p>
            <?php endif; ?>
        </header>

        <section class="map-shell" aria-label="<?php esc_attr_e( 'Route map', 'travel-app' ); ?>">
            <div id="route-map"></div>
            <div class="map-status" data-map-status><?php esc_html_e( 'Loading route map...', 'travel-app' ); ?></div>
        </section>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
            var entries = <?php echo wp_json_encode( array_values( $route_entries ) ); ?>;
            var status = document.querySelector('[data-map-status]');
            var mapNode = document.getElementById('route-map');

            function setStatus(message) {
                if (status) {
                    status.textContent = message || '';
                }
            }

            if (!mapNode || typeof L === 'undefined') {
                setStatus('<?php echo esc_js( __( 'The map library could not be loaded.', 'travel-app' ) ); ?>');
                return;
            }

            var map = L.map(mapNode);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            if (entries.length < 2) {
                map.setView([0, 0], 2);
                setStatus('<?php echo esc_js( __( 'Add at least two itinerary locations to draw a route.', 'travel-app' ) ); ?>');
                return;
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function(character) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[character];
                });
            }

            function wait(milliseconds) {
                return new Promise(function(resolve) {
                    window.setTimeout(resolve, milliseconds);
                });
            }

            function geocode(entry) {
                var url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('limit', '1');
                url.searchParams.set('q', entry.location);

                return fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(function(response) {
                    if (!response.ok) {
                        throw new Error('Geocoding failed');
                    }

                    return response.json();
                }).then(function(results) {
                    if (!results.length) {
                        return null;
                    }

                    return {
                        entry: entry,
                        lat: parseFloat(results[0].lat),
                        lon: parseFloat(results[0].lon)
                    };
                });
            }

            function geocodeLocations(items) {
                var points = [];
                var cache = {};

                return items.reduce(function(chain, entry, index) {
                    return chain.then(function() {
                        if (cache[entry.location]) {
                            points.push(Object.assign({}, cache[entry.location], { entry: entry }));
                            return null;
                        }

                        return geocode(entry).then(function(point) {
                            cache[entry.location] = point;
                            points.push(point);

                            return index < items.length - 1 ? wait(1100) : null;
                        });
                    });
                }, Promise.resolve()).then(function() {
                    return points;
                });
            }

            geocodeLocations(entries).then(function(points) {
                var routePoints = points.filter(function(point) {
                    return point && isFinite(point.lat) && isFinite(point.lon);
                });

                if (routePoints.length < 2) {
                    map.setView([0, 0], 2);
                    setStatus('<?php echo esc_js( __( 'Not enough locations could be found on OpenStreetMap.', 'travel-app' ) ); ?>');
                    return;
                }

                routePoints.forEach(function(point, index) {
                    var marker = L.divIcon({
                        className: '',
                        html: '<span class="route-marker">' + String(index + 1) + '</span>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13]
                    });
                    var entry = point.entry || {};
                    var dateTime = [entry.date, entry.time].filter(Boolean).join(' ');
                    var meta = [entry.type, entry.kind, dateTime].filter(Boolean).join(' · ');
                    var title = escapeHtml(entry.title || '<?php echo esc_js( __( 'Untitled item', 'travel-app' ) ); ?>');
                    var popupHtml = [
                        '<div class="route-popup">',
                        '<div class="route-popup-title">' + (entry.url ? '<a href="' + encodeURI(entry.url) + '">' + title + '</a>' : title) + '</div>',
                        meta ? '<div class="route-popup-meta">' + escapeHtml(meta) + '</div>' : '',
                        '<div class="route-popup-location">' + escapeHtml(entry.location || '') + '</div>',
                        entry.details ? '<div class="route-popup-details">' + escapeHtml(entry.details) + '</div>' : '',
                        '</div>'
                    ].join('');

                    L.marker([point.lat, point.lon], { icon: marker })
                        .bindPopup(popupHtml)
                        .addTo(map);
                });

                var coordinates = routePoints.map(function(point) {
                    return [point.lat, point.lon];
                });
                var line = L.polyline(coordinates, {
                    color: '#0b6bcb',
                    weight: 4,
                    opacity: 0.84
                }).addTo(map);

                map.fitBounds(line.getBounds(), { padding: [28, 28] });

                var missing = entries.length - routePoints.length;
                setStatus(missing > 0
                    ? missing + ' ' + (missing === 1
                        ? '<?php echo esc_js( __( 'location could not be found and was skipped.', 'travel-app' ) ); ?>'
                        : '<?php echo esc_js( __( 'locations could not be found and were skipped.', 'travel-app' ) ); ?>')
                    : ''
                );
            }).catch(function() {
                map.setView([0, 0], 2);
                setStatus('<?php echo esc_js( __( 'The route map could not geocode the itinerary locations.', 'travel-app' ) ); ?>');
            });
        })();
    </script>
    <?php wp_app_body_close(); ?>
</body>
</html>
