<?php
use Traveler\App;
use Traveler\GeocodeCache;
use Traveler\Trip;

global $wp_app_route;

$traveler = App::get_instance();
$trip_id    = isset( $wp_app_route['params']['id'] ) ? absint( $wp_app_route['params']['id'] ) : absint( get_query_var( 'id' ) );
$trip       = Trip::get( $trip_id );
if ( ! $trip || ! current_user_can( 'read_traveler_trip', $trip_id ) ) {
    wp_die(
        esc_html__( 'This travel plan could not be found.', 'traveler' ),
        esc_html__( 'Travel plan not found', 'traveler' ),
        [ 'response' => 404 ]
    );
}

$trip_data = $trip->to_array();
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
                'id'       => (int) ( $segment['id'] ?? count( $route_entries ) ),
                'is_end'   => 'end_location' === $location_key,
                'location' => $location,
                'kind'     => 'end_location' === $location_key ? __( 'End location', 'traveler' ) : __( 'Location', 'traveler' ),
                'title'    => (string) ( $segment['title'] ?: __( 'Untitled item', 'traveler' ) ),
                'type'     => (string) ( $segment['type'] ?? '' ),
                'date'     => (string) ( $segment['date'] ?? '' ),
                'time'     => (string) ( $segment['time'] ?? '' ),
                'details'  => (string) ( $segment['details'] ?? '' ),
                'url'      => home_url( '/traveler/trip/' . $trip_id . '/#segment-' . (int) ( $segment['id'] ?? 0 ) ),
            ];
        }
    }
}

$route_location_names = [];
foreach ( $route_entries as $route_entry ) {
    if ( ! in_array( $route_entry['location'], $route_location_names, true ) ) {
        $route_location_names[] = $route_entry['location'];
    }
}

// Coordinates this site already looked up, so a revisited trip draws at once.
$known_locations = GeocodeCache::get_many( $route_location_names );

$map_strings = [
    'library'         => __( 'The map library could not be loaded.', 'traveler' ),
    'too_few'         => __( 'Add at least two itinerary locations to draw a route.', 'traveler' ),
    'untitled'        => __( 'Untitled item', 'traveler' ),
    'cached'          => __( 'Cached coordinates', 'traveler' ),
    'queued'          => __( 'Waiting for its turn', 'traveler' ),
    'looking'         => __( 'Looking it up on OpenStreetMap...', 'traveler' ),
    'found'           => __( 'Looked up on OpenStreetMap', 'traveler' ),
    'missing'         => __( 'OpenStreetMap does not know this place', 'traveler' ),
    'failed'          => __( 'The lookup failed', 'traveler' ),
    'hidden'          => __( 'Hidden from the map', 'traveler' ),
    'auto_pick'       => __( 'Best match for this itinerary', 'traveler' ),
    /* translators: %s: number of the match in the list of possible places. */
    'pick_number'     => __( 'Match %s', 'traveler' ),
    /* translators: 1: number of the location being looked up, 2: number of locations to look up. */
    'progress'        => __( 'Looking up location %1$s of %2$s on OpenStreetMap. It allows one lookup per second; results are then cached.', 'traveler' ),
    /* translators: 1: number of waypoints shown, 2: number of waypoints in the itinerary. */
    'summary_points'  => __( '%1$s of %2$s waypoints on the map', 'traveler' ),
    /* translators: %s: number of waypoints whose coordinates were already known. */
    'summary_cached'  => __( '%s from the cache', 'traveler' ),
    /* translators: %s: number of waypoints looked up on OpenStreetMap just now. */
    'summary_looked'  => __( '%s looked up', 'traveler' ),
    /* translators: %s: number of waypoints that could not be found. */
    'summary_missing' => __( '%s not found', 'traveler' ),
    'summary_none'    => __( 'None of the itinerary locations could be placed on the map.', 'traveler' ),
];
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wp_app_title( sprintf( __( '%s Route Map', 'traveler' ), $trip_data['title'] ) ); ?></title>
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
            margin: 10px 0 0;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 8px 12px;
            background: var(--wp-app-color-surface);
            color: var(--wp-app-color-muted);
            font-size: 0.9rem;
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
        .route-list { margin-top: 22px; }
        .route-list-head {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px 16px;
        }
        .route-list-head h2 {
            margin: 0;
            font-size: 1.1rem;
        }
        .route-list-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .route-list-actions button {
            border: 1px solid var(--wp-app-color-border);
            border-radius: 999px;
            padding: 4px 12px;
            background: var(--wp-app-color-surface);
            color: var(--wp-app-color-text);
            font: inherit;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .route-list-items {
            list-style: none;
            display: grid;
            gap: 8px;
            margin: 12px 0 0;
            padding: 0;
        }
        .route-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid var(--wp-app-color-border);
            border-radius: 8px;
            padding: 10px 12px;
            background: var(--wp-app-color-surface);
        }
        .route-item[data-route-hidden] { opacity: 0.55; }
        .route-item-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .route-item-number {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #0b6bcb;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 800;
        }
        .route-item[data-route-hidden] .route-item-number,
        .route-item[data-route-unplaced] .route-item-number {
            background: var(--wp-app-color-border);
            color: var(--wp-app-color-muted);
        }
        .route-item-body {
            flex: 1 1 auto;
            min-width: 0;
        }
        .route-item-focus {
            border: 0;
            padding: 0;
            background: none;
            color: var(--wp-app-color-link);
            font: inherit;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
        }
        .route-item-meta,
        .route-item-status {
            color: var(--wp-app-color-muted);
            font-size: 0.85rem;
        }
        .route-item-status { margin-top: 2px; }
        .route-item-pick { display: block; margin-top: 6px; }
        .route-item-pick select {
            width: 100%;
            max-width: 420px;
            font: inherit;
            font-size: 0.85rem;
        }
        .screen-reader-text {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(1px, 1px, 1px, 1px);
            white-space: nowrap;
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
                <a href="<?php echo esc_url( home_url( '/traveler/trip/' . $trip_id . '/' ) ); ?>"><?php esc_html_e( 'Back to Travel Plan', 'traveler' ); ?></a>
            </div>

            <?php if ( ! $trip_data ) : ?>
                <h1><?php esc_html_e( 'Travel plan not found', 'traveler' ); ?></h1>
                <p class="meta"><?php esc_html_e( 'It may have been deleted, or it does not belong to your account.', 'traveler' ); ?></p>
            <?php else : ?>
                <h1>
                    <?php
                    printf(
                        /* translators: %s: travel plan title. */
                        esc_html__( '%s Route Map', 'traveler' ),
                        '<span' . App::mask_attr( 'title', (string) $trip_data['id'] ) . '>' . esc_html( $trip_data['title'] ) . '</span>'
                    );
                    ?>
                </h1>
                <p class="meta">
                    <span><?php echo esc_html( sprintf( _n( '%d waypoint', '%d waypoints', count( $route_entries ), 'traveler' ), count( $route_entries ) ) ); ?></span>
                    <span><?php esc_html_e( 'Straight lines between itinerary locations', 'traveler' ); ?></span>
                </p>
            <?php endif; ?>
        </header>

        <section class="map-shell" aria-label="<?php esc_attr_e( 'Route map', 'traveler' ); ?>">
            <div id="route-map"></div>
        </section>

        <p class="map-status" data-map-status><?php esc_html_e( 'Loading route map...', 'traveler' ); ?></p>

        <?php if ( $route_entries ) : ?>
        <section class="route-list" aria-label="<?php esc_attr_e( 'Waypoints', 'traveler' ); ?>">
            <div class="route-list-head">
                <h2><?php esc_html_e( 'Waypoints', 'traveler' ); ?></h2>
                <div class="route-list-actions">
                    <button type="button" data-route-select="all"><?php esc_html_e( 'Show all', 'traveler' ); ?></button>
                    <button type="button" data-route-select="none"><?php esc_html_e( 'Hide all', 'traveler' ); ?></button>
                    <button type="button" data-route-refresh><?php esc_html_e( 'Look up again', 'traveler' ); ?></button>
                </div>
            </div>

            <ol class="route-list-items">
                <?php foreach ( $route_entries as $route_index => $route_entry ) : ?>
                    <?php
                    $route_meta = array_filter( [
                        $route_entry['type'],
                        $route_entry['kind'],
                        trim( $route_entry['date'] . ' ' . $route_entry['time'] ),
                    ] );
                    $route_key = (string) $route_entry['id'];
                    ?>
                    <li class="route-item" data-route-item="<?php echo esc_attr( (string) $route_index ); ?>">
                        <label class="route-item-check">
                            <input type="checkbox" checked data-route-toggle="<?php echo esc_attr( (string) $route_index ); ?>">
                            <span class="route-item-number" data-route-number aria-hidden="true">-</span>
                        </label>
                        <div class="route-item-body">
                            <button type="button" class="route-item-focus" data-route-focus="<?php echo esc_attr( (string) $route_index ); ?>"><span<?php echo App::mask_attr( 'title', $route_key . '-item' ); ?>><?php echo esc_html( $route_entry['title'] ); ?></span></button>
                            <?php if ( $route_meta ) : ?>
                                <div class="route-item-meta"><?php echo esc_html( implode( ' · ', $route_meta ) ); ?></div>
                            <?php endif; ?>
                            <div class="route-item-meta"<?php echo App::mask_attr( 'place', $route_key . ( $route_entry['is_end'] ? '-end-location' : '-location' ) ); ?>><?php echo esc_html( $route_entry['location'] ); ?></div>
                            <div class="route-item-status" data-route-status></div>
                            <div class="route-item-status" data-route-match<?php echo App::mask_attr( 'place' ); ?>></div>
                            <label class="route-item-pick" hidden>
                                <span class="screen-reader-text"><?php esc_html_e( 'Matched place', 'traveler' ); ?></span>
                                <select data-route-pick="<?php echo esc_attr( (string) $route_index ); ?>"></select>
                            </label>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
            var entries = <?php echo wp_json_encode( array_values( $route_entries ) ); ?>;
            var seeded = <?php echo wp_json_encode( (object) $known_locations ); ?>;
            var i18n = <?php echo wp_json_encode( $map_strings ); ?>;
            var demoMode = <?php echo $traveler->is_demo_mode_enabled() ? 'true' : 'false'; ?>;
            var ajax = <?php echo wp_json_encode( [
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'traveler_geocode' ),
            ] ); ?>;
            var status = document.querySelector('[data-map-status]');
            var mapNode = document.getElementById('route-map');
            // Nominatim allows one lookup per second, so a fresh itinerary takes a while. What it
            // answers does not change, so coordinates are kept both in this browser and, through
            // admin-ajax, on the site itself, and the route is drawn as waypoints arrive.
            var CACHE_KEY = 'traveler-geocode-v2';
            var PICK_KEY = 'traveler-geocode-picks-v1';
            var CACHE_TTL = 30 * 24 * 60 * 60 * 1000;
            var MISS_TTL = 24 * 60 * 60 * 1000;
            var LOOKUP_DELAY = 1100;
            var CANDIDATES = 5;
            var OUTLIER_KM = 300;
            var OVERRIDE_RATIO = 0.5;
            var BOX_PADDING = 1.5;

            function setStatus(message) {
                if (status) {
                    status.textContent = message || '';
                }
            }

            function format(template, values) {
                var index = 0;

                return String(template).replace(/%(?:(\d+)\$)?s/g, function(match, position) {
                    return String(values[position ? position - 1 : index++]);
                });
            }

            if (!mapNode || typeof L === 'undefined') {
                setStatus(i18n.library);
                return;
            }

            var map = L.map(mapNode);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            map.setView([20, 0], 2);

            if (entries.length < 2) {
                setStatus(i18n.too_few);
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

            function maskAttr(type, key) {
                key = String(key || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
                return ' data-' + type + (key ? '-' + key : '');
            }

            function wait(milliseconds) {
                return new Promise(function(resolve) {
                    window.setTimeout(resolve, milliseconds);
                });
            }

            function readStore(key) {
                try {
                    return JSON.parse(window.localStorage.getItem(key)) || {};
                } catch (error) {
                    return {};
                }
            }

            function writeStore(key, value) {
                try {
                    window.localStorage.setItem(key, JSON.stringify(value));
                } catch (error) {
                    // Storage being full or unavailable only costs us the lookups next time.
                }
            }

            // Returns the cached candidate list, an empty list for a location known not to exist,
            // or undefined when the location still has to be looked up.
            function cachedCandidates(cache, location) {
                var record = cache[location];

                if (!record || !record.time || !record.candidates) {
                    return undefined;
                }

                if (Date.now() - record.time > (record.candidates.length ? CACHE_TTL : MISS_TTL)) {
                    return undefined;
                }

                return record.candidates;
            }

            // Nominatim rejects a hotel or venue name it does not know, so fall back to the
            // increasingly generic parts of the location, but never to a bare country name.
            function queryVariants(location) {
                var variants = [];
                var parts = String(location).split(',').map(function(part) {
                    return part.trim();
                }).filter(Boolean);

                function add(value) {
                    value = String(value || '').replace(/\s+/g, ' ').trim();

                    if (value.length > 2 && -1 === variants.indexOf(value)) {
                        variants.push(value);
                    }
                }

                add(location);
                add(String(location).replace(/\([^)]*\)/g, ' '));

                if (parts.length > 1) {
                    add(parts.slice(1).join(', '));
                    add(parts.slice(-2).join(', '));
                }

                return variants;
            }

            var nextLookup = 0;

            function search(query, box) {
                var url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'jsonv2');
                url.searchParams.set('limit', String(CANDIDATES));
                url.searchParams.set('dedupe', '1');
                url.searchParams.set('q', query);

                if (box) {
                    url.searchParams.set('viewbox', box.join(','));
                    url.searchParams.set('bounded', '1');
                }

                // Keep at least one second between requests, as the usage policy requires.
                return wait(Math.max(0, nextLookup - Date.now())).then(function() {
                    nextLookup = Date.now() + LOOKUP_DELAY;

                    return fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                }).then(function(response) {
                    if (!response.ok) {
                        throw new Error('Geocoding failed');
                    }

                    return response.json();
                }).then(function(results) {
                    return results.map(function(result) {
                        return {
                            lat: parseFloat(result.lat),
                            lon: parseFloat(result.lon),
                            importance: parseFloat(result.importance) || 0,
                            label: String(result.display_name || '')
                        };
                    }).filter(function(candidate) {
                        return isFinite(candidate.lat) && isFinite(candidate.lon);
                    });
                });
            }

            // Remembers which query actually answered, so a hit from a shortened fallback can be
            // double-checked against the rest of the itinerary afterwards.
            function geocode(location) {
                return queryVariants(location).reduce(function(chain, query, position) {
                    return chain.then(function(result) {
                        if (result.candidates.length) {
                            return result;
                        }

                        return search(query).then(function(candidates) {
                            return { candidates: candidates, query: query, fallback: position > 0 };
                        });
                    });
                }, Promise.resolve({ candidates: [], query: location, fallback: false }));
            }

            function distanceKm(a, b) {
                var radius = 6371;
                var toRadians = Math.PI / 180;
                var deltaLat = (b.lat - a.lat) * toRadians;
                var deltaLon = (b.lon - a.lon) * toRadians;
                var h = Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2)
                    + Math.cos(a.lat * toRadians) * Math.cos(b.lat * toRadians)
                    * Math.sin(deltaLon / 2) * Math.sin(deltaLon / 2);

                return 2 * radius * Math.asin(Math.min(1, Math.sqrt(h)));
            }

            var cache = readStore(CACHE_KEY);
            var picks = readStore(PICK_KEY);
            var candidates = {};
            var states = {};
            var fallbacks = {};
            var chosen = {};
            var hidden = {};
            var markers = {};
            var layer = L.layerGroup().addTo(map);
            var rows = {};
            var userMoved = false;
            var lookingUp = false;

            Array.prototype.forEach.call(document.querySelectorAll('[data-route-item]'), function(row) {
                rows[row.getAttribute('data-route-item')] = row;
            });

            map.on('dragstart', function() {
                userMoved = true;
            });

            map.on('popupopen', function(event) {
                if (window.maskPrivateData && typeof window.maskPrivateData.process === 'function' && event.popup) {
                    window.maskPrivateData.process(event.popup.getElement());
                }
            });

            function visibleEntries() {
                return entries.filter(function(entry, index) {
                    return !hidden[index];
                });
            }

            // A place name is rarely unique, so the neighbouring waypoints decide which candidate
            // is meant: the itinerary is the only context that tells one Springfield from another.
            // Importance breaks ties when nothing has been placed nearby yet.
            function chooseCandidates() {
                var visible = visibleEntries();

                Object.keys(candidates).forEach(function(location) {
                    var options = candidates[location];
                    var pinned = picks[location] ? options.filter(function(option) {
                        return option.label === picks[location];
                    })[0] : null;

                    chosen[location] = pinned || chosen[location] || options[0] || null;
                });

                // Two sweeps so a location settles against both its earlier and its later
                // neighbours, whichever of them was placed first.
                for (var sweep = 0; sweep < 2; sweep++) {
                    visible.forEach(function(entry, index) {
                        var options = candidates[entry.location];

                        if (!options || options.length < 2 || picks[entry.location]) {
                            return;
                        }

                        var anchors = [];

                        [index - 1, index + 1].forEach(function(position) {
                            var other = visible[position];

                            if (other && other.location !== entry.location && chosen[other.location]) {
                                anchors.push(chosen[other.location]);
                            }
                        });

                        if (!anchors.length) {
                            return;
                        }

                        // Nominatim ranks its results, so the first one wins unless it is an
                        // outlier and another candidate is dramatically closer to the itinerary.
                        // A flight leg is legitimately far from its neighbour; a place on the
                        // wrong continent is not.
                        var favourite = options[0];
                        var favouriteKm = nearestAnchorKm(favourite, anchors);
                        var best = favourite;
                        var bestKm = favouriteKm;

                        options.forEach(function(option) {
                            var km = nearestAnchorKm(option, anchors);

                            if (km < bestKm) {
                                bestKm = km;
                                best = option;
                            }
                        });

                        chosen[entry.location] = (favouriteKm > OUTLIER_KM && bestKm < favouriteKm * OVERRIDE_RATIO)
                            ? best
                            : favourite;
                    });
                }
            }

            function popupHtml(entry, index) {
                var entryKey = String(entry.id || index);
                var dateTime = [entry.date, entry.time].filter(Boolean).join(' ');
                var meta = [entry.type, entry.kind, dateTime].filter(Boolean).join(' · ');
                var title = escapeHtml(entry.title || i18n.untitled);
                var maskedTitle = '<span' + maskAttr('title', entryKey + '-item') + '>' + title + '</span>';
                var locationKey = entryKey + (entry.is_end ? '-end-location' : '-location');

                return [
                    '<div class="route-popup">',
                    '<div class="route-popup-title">' + (entry.url ? '<a href="' + encodeURI(entry.url) + '">' + maskedTitle + '</a>' : maskedTitle) + '</div>',
                    meta ? '<div class="route-popup-meta">' + escapeHtml(meta) + '</div>' : '',
                    '<div class="route-popup-location"' + maskAttr('place', locationKey) + '>' + escapeHtml(entry.location || '') + '</div>',
                    entry.details ? '<div class="route-popup-details"' + maskAttr('text', entryKey + '-details') + '>' + escapeHtml(entry.details) + '</div>' : '',
                    '</div>'
                ].join('');
            }

            function renderRoute() {
                chooseCandidates();

                var routePoints = [];

                entries.forEach(function(entry, index) {
                    var point = hidden[index] ? null : chosen[entry.location];

                    if (point) {
                        routePoints.push({ entry: entry, index: index, lat: point.lat, lon: point.lon });
                    }
                });

                var coordinates = routePoints.map(function(point) {
                    return [point.lat, point.lon];
                });

                layer.clearLayers();
                markers = {};

                if (coordinates.length > 1) {
                    L.polyline(coordinates, {
                        color: '#0b6bcb',
                        weight: 4,
                        opacity: 0.84
                    }).addTo(layer);
                }

                routePoints.forEach(function(point, position) {
                    var icon = L.divIcon({
                        className: '',
                        html: '<span class="route-marker">' + String(position + 1) + '</span>',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13]
                    });

                    // Keys mirror the itinerary markup so a place keeps the same replacement in both views.
                    markers[point.index] = L.marker([point.lat, point.lon], { icon: icon })
                        .bindPopup(popupHtml(point.entry, point.index))
                        .addTo(layer);
                });

                if (coordinates.length && !userMoved) {
                    map.fitBounds(L.latLngBounds(coordinates), {
                        padding: [28, 28],
                        maxZoom: coordinates.length > 1 ? 19 : 11
                    });
                }

                updateRows(routePoints);

                return routePoints;
            }

            function statusText(index, entry) {
                if (hidden[index]) {
                    return i18n.hidden;
                }

                return {
                    cached: i18n.cached,
                    queued: i18n.queued,
                    looking: i18n.looking,
                    found: i18n.found,
                    missing: i18n.missing,
                    failed: i18n.failed
                }[states[entry.location] || 'queued'] || '';
            }

            // The matched place comes from OpenStreetMap after the page was masked, so it has to
            // carry its own value for Mask Private Data and be run through it again.
            function showMatch(row, entry, placed) {
                var node = row.querySelector('[data-route-match]');

                if (!node) {
                    return;
                }

                var label = placed && !demoMode && chosen[entry.location] ? chosen[entry.location].label : '';

                node.textContent = label;
                node.setAttribute('data-private-value', label);

                if (label && window.maskPrivateData && typeof window.maskPrivateData.process === 'function') {
                    window.maskPrivateData.process(node);
                }
            }

            function updateRows(routePoints) {
                var numbers = {};

                routePoints.forEach(function(point, position) {
                    numbers[point.index] = position + 1;
                });

                entries.forEach(function(entry, index) {
                    var row = rows[String(index)];

                    if (!row) {
                        return;
                    }

                    var placed = Boolean(numbers[index]);
                    var number = row.querySelector('[data-route-number]');
                    var statusNode = row.querySelector('[data-route-status]');
                    var pick = row.querySelector('[data-route-pick]');
                    var options = candidates[entry.location] || [];

                    if (number) {
                        number.textContent = placed ? String(numbers[index]) : '-';
                    }

                    if (statusNode) {
                        statusNode.textContent = statusText(index, entry);
                    }

                    showMatch(row, entry, placed);

                    if (hidden[index]) {
                        row.setAttribute('data-route-hidden', '');
                    } else {
                        row.removeAttribute('data-route-hidden');
                    }

                    if (placed) {
                        row.removeAttribute('data-route-unplaced');
                    } else {
                        row.setAttribute('data-route-unplaced', '');
                    }

                    if (!pick) {
                        return;
                    }

                    var signature = entry.location + '|' + options.length;

                    if (pick.getAttribute('data-route-signature') !== signature) {
                        pick.setAttribute('data-route-signature', signature);
                        pick.innerHTML = '';

                        var auto = document.createElement('option');
                        auto.value = '';
                        auto.textContent = i18n.auto_pick;
                        pick.appendChild(auto);

                        options.forEach(function(option, position) {
                            var choice = document.createElement('option');
                            choice.value = option.label;
                            choice.textContent = demoMode ? format(i18n.pick_number, [ position + 1 ]) : option.label;
                            pick.appendChild(choice);
                        });
                    }

                    pick.value = picks[entry.location] || '';
                    pick.parentNode.hidden = options.length < 2;
                });
            }

            function summarize() {
                var placed = 0;
                var fromCache = 0;
                var lookedUp = 0;
                var missing = 0;

                entries.forEach(function(entry, index) {
                    if (!chosen[entry.location]) {
                        missing++;
                        return;
                    }

                    if (!hidden[index]) {
                        placed++;
                    }

                    if ('cached' === states[entry.location]) {
                        fromCache++;
                    } else if ('found' === states[entry.location]) {
                        lookedUp++;
                    }
                });

                if (!placed) {
                    setStatus(i18n.summary_none);
                    return;
                }

                var parts = [ format(i18n.summary_points, [ placed, entries.length ]) ];

                if (fromCache) {
                    parts.push(format(i18n.summary_cached, [ fromCache ]));
                }

                if (lookedUp) {
                    parts.push(format(i18n.summary_looked, [ lookedUp ]));
                }

                if (missing) {
                    parts.push(format(i18n.summary_missing, [ missing ]));
                }

                setStatus(parts.join(' · '));
            }

            function persist(found) {
                var locations = Object.keys(found);

                if (!locations.length || !ajax.nonce) {
                    return;
                }

                var body = new URLSearchParams();
                body.set('action', 'traveler_cache_geocode');
                body.set('nonce', ajax.nonce);
                body.set('locations', JSON.stringify(found));

                fetch(ajax.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                }).catch(function() {
                    // The browser cache still has them; the site cache can miss out.
                });
            }

            function anchorsFor(location) {
                var visible = visibleEntries();
                var anchors = [];

                visible.forEach(function(entry, index) {
                    if (entry.location !== location) {
                        return;
                    }

                    [ index - 1, index + 1 ].forEach(function(position) {
                        var other = visible[position];

                        if (other && other.location !== location && chosen[other.location]) {
                            anchors.push(chosen[other.location]);
                        }
                    });
                });

                return anchors;
            }

            function nearestAnchorKm(point, anchors) {
                return anchors.reduce(function(shortest, anchor) {
                    return Math.min(shortest, distanceKm(point, anchor));
                }, Infinity);
            }

            // A shortened query such as a bare "Chianti" can land on the other side of the world.
            // When the hit came from such a fallback and sits far from its neighbours, ask again
            // inside the box the neighbours span - that is where the itinerary actually goes.
            function refine(found) {
                var suspects = Object.keys(fallbacks).filter(function(location) {
                    var point = chosen[location];
                    var anchors = point ? anchorsFor(location) : [];

                    return anchors.length && nearestAnchorKm(point, anchors) > OUTLIER_KM;
                });

                return suspects.reduce(function(chain, location) {
                    return chain.then(function() {
                        var anchors = anchorsFor(location);
                        var lats = anchors.map(function(anchor) { return anchor.lat; });
                        var lons = anchors.map(function(anchor) { return anchor.lon; });
                        var box = [
                            Math.min.apply(null, lons) - BOX_PADDING,
                            Math.max.apply(null, lats) + BOX_PADDING,
                            Math.max.apply(null, lons) + BOX_PADDING,
                            Math.min.apply(null, lats) - BOX_PADDING
                        ];

                        return search(fallbacks[location], box).then(function(results) {
                            if (!results.length) {
                                return;
                            }

                            candidates[location] = results;
                            delete chosen[location];
                            cache[location] = { candidates: results, time: Date.now() };
                            writeStore(CACHE_KEY, cache);
                            found[location] = results;
                            renderRoute();
                        }).catch(function() {
                            // Keeping the far-off match is better than losing the waypoint.
                        });
                    });
                }, Promise.resolve());
            }

            function lookUp(pending) {
                if (lookingUp || !pending.length) {
                    summarize();
                    return Promise.resolve();
                }

                lookingUp = true;

                var found = {};

                return pending.reduce(function(chain, location, index) {
                    return chain.then(function() {
                        states[location] = 'looking';
                        setStatus(format(i18n.progress, [ index + 1, pending.length ]));
                        renderRoute();

                        return geocode(location).then(function(result) {
                            var results = result.candidates;

                            candidates[location] = results;
                            states[location] = results.length ? 'found' : 'missing';
                            cache[location] = { candidates: results, time: Date.now() };
                            writeStore(CACHE_KEY, cache);

                            if (results.length) {
                                found[location] = results;
                            }

                            if (results.length && result.fallback) {
                                fallbacks[location] = result.query;
                            }
                        }).catch(function() {
                            // One failed lookup should not stop the rest of the itinerary.
                            states[location] = 'failed';
                        }).then(function() {
                            renderRoute();
                        });
                    });
                }, Promise.resolve()).then(function() {
                    return refine(found);
                }).then(function() {
                    lookingUp = false;
                    persist(found);
                    summarize();
                });
            }

            function collectPending() {
                var pending = [];

                entries.forEach(function(entry) {
                    if (Object.prototype.hasOwnProperty.call(candidates, entry.location)) {
                        return;
                    }

                    var known = cachedCandidates(cache, entry.location);

                    if (undefined === known) {
                        if (-1 === pending.indexOf(entry.location)) {
                            states[entry.location] = 'queued';
                            pending.push(entry.location);
                        }

                        return;
                    }

                    candidates[entry.location] = known;
                    states[entry.location] = known.length ? 'cached' : 'missing';
                });

                return pending;
            }

            document.addEventListener('change', function(event) {
                var toggle = event.target.closest ? event.target.closest('[data-route-toggle]') : null;

                if (toggle) {
                    hidden[toggle.getAttribute('data-route-toggle')] = !toggle.checked;
                    renderRoute();
                    summarize();
                    return;
                }

                var pick = event.target.closest ? event.target.closest('[data-route-pick]') : null;

                if (pick) {
                    var entry = entries[pick.getAttribute('data-route-pick')];

                    if (entry) {
                        if (pick.value) {
                            picks[entry.location] = pick.value;
                        } else {
                            delete picks[entry.location];
                            delete chosen[entry.location];
                        }

                        writeStore(PICK_KEY, picks);
                        userMoved = false;
                        renderRoute();
                    }
                }
            });

            document.addEventListener('click', function(event) {
                var focus = event.target.closest ? event.target.closest('[data-route-focus]') : null;

                if (focus) {
                    var marker = markers[focus.getAttribute('data-route-focus')];

                    if (marker) {
                        userMoved = true;
                        map.setView(marker.getLatLng(), Math.max(map.getZoom(), 11));
                        marker.openPopup();
                        document.querySelector('.map-shell').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }

                    return;
                }

                var select = event.target.closest ? event.target.closest('[data-route-select]') : null;

                if (select) {
                    var show = 'all' === select.getAttribute('data-route-select');

                    entries.forEach(function(entry, index) {
                        hidden[index] = !show;
                        var toggle = rows[String(index)] && rows[String(index)].querySelector('[data-route-toggle]');

                        if (toggle) {
                            toggle.checked = show;
                        }
                    });

                    userMoved = false;
                    renderRoute();
                    summarize();
                    return;
                }

                if (event.target.closest && event.target.closest('[data-route-refresh]') && !lookingUp) {
                    // Forget what we know so a wrong or stale match can be looked up again.
                    entries.forEach(function(entry) {
                        delete cache[entry.location];
                        delete candidates[entry.location];
                        delete chosen[entry.location];
                        delete picks[entry.location];
                        delete fallbacks[entry.location];
                    });

                    writeStore(CACHE_KEY, cache);
                    writeStore(PICK_KEY, picks);
                    userMoved = false;
                    renderRoute();
                    lookUp(collectPending());
                }
            });

            // Coordinates this site looked up before are on the page already.
            Object.keys(seeded).forEach(function(location) {
                candidates[location] = seeded[location];
                states[location] = 'cached';
            });

            var queue = collectPending();

            renderRoute();
            lookUp(queue);
        })();
    </script>
    <?php wp_app_body_close(); ?>
</body>
</html>
