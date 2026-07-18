<?php

namespace TravelApp;

class UrlPreviewService {
    public function normalize_preview( $preview ): array {
        $preview = is_array( $preview ) ? $preview : [];

        return [
            'title'       => sanitize_text_field( (string) ( $preview['title'] ?? '' ) ),
            'description' => sanitize_text_field( (string) ( $preview['description'] ?? '' ) ),
            'image'       => esc_url_raw( (string) ( $preview['image'] ?? '' ) ),
        ];
    }

    public function get_item_preview( int $item_id ): array {
        $preview = get_post_meta( $item_id, '_travel_app_url_preview', true );
        if ( ! is_array( $preview ) ) {
            return [];
        }

        return [
            'title'       => sanitize_text_field( (string) ( $preview['title'] ?? '' ) ),
            'description' => sanitize_text_field( (string) ( $preview['description'] ?? '' ) ),
            'image'       => esc_url_raw( (string) ( $preview['image'] ?? '' ) ),
            'site_name'   => sanitize_text_field( (string) ( $preview['site_name'] ?? '' ) ),
            'url'         => esc_url_raw( (string) ( $preview['url'] ?? '' ) ),
        ];
    }

    public function get_item_preview_debug( int $item_id ): array {
        $debug = get_post_meta( $item_id, '_travel_app_url_preview_debug', true );
        if ( ! is_array( $debug ) ) {
            return [];
        }

        return array_map( static function( $value ): string {
            return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
        }, $debug );
    }

    public function sync_item_preview( int $item_id, array $segment, string $previous_url ): void {
        $manual_preview = $this->prepare_manual_preview( (string) $segment['url'], (array) $segment['url_preview'] );
        if ( ! empty( $manual_preview ) ) {
            update_post_meta( $item_id, '_travel_app_url_preview', $manual_preview );
            update_post_meta( $item_id, '_travel_app_url_preview_debug', [
                'status'     => 'manual',
                'message'    => __( 'Preview metadata was entered manually.', 'travel-app' ),
                'fetched_at' => current_time( 'mysql' ),
            ] );
            return;
        }

        if ( $segment['url'] !== $previous_url || ( '' !== $segment['url'] && ! get_post_meta( $item_id, '_travel_app_url_preview', true ) ) ) {
            $this->refresh_item_preview( $item_id, $segment );
        }
    }

    private function prepare_manual_preview( string $url, array $preview ): array {
        if ( '' === $url ) {
            return [];
        }

        $preview = $this->normalize_preview( $preview );
        if ( '' === $preview['title'] && '' === $preview['description'] && '' === $preview['image'] ) {
            return [];
        }

        $preview['site_name'] = sanitize_text_field( (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?? '' ) );
        $preview['url'] = esc_url_raw( $url );

        return $preview;
    }

    private function refresh_item_preview( int $item_id, array $segment ): void {
        $url = (string) ( $segment['url'] ?? '' );
        if ( '' === $url ) {
            delete_post_meta( $item_id, '_travel_app_url_preview' );
            delete_post_meta( $item_id, '_travel_app_url_preview_debug' );
            return;
        }

        if ( $this->is_google_maps_url( $url ) ) {
            $maps_details = $this->get_google_maps_url_details( $url );
            if ( ! empty( $maps_details['address'] ) && empty( $segment['location'] ) ) {
                update_post_meta( $item_id, '_travel_app_location', $maps_details['address'] );
                $segment['location'] = $maps_details['address'];
            }

            update_post_meta( $item_id, '_travel_app_url_preview', $this->get_google_maps_url_preview( $segment, $maps_details ) );
            update_post_meta( $item_id, '_travel_app_url_preview_debug', [
                'status'     => 'google_maps',
                'message'    => __( 'Google Maps URL preview was derived from the item details.', 'travel-app' ),
                'fetched_at' => current_time( 'mysql' ),
            ] );
            return;
        }

        $debug = [];
        $preview = $this->fetch_url_preview( $url, $debug );
        update_post_meta( $item_id, '_travel_app_url_preview_debug', $debug );

        if ( empty( $preview ) ) {
            delete_post_meta( $item_id, '_travel_app_url_preview' );
            return;
        }

        update_post_meta( $item_id, '_travel_app_url_preview', $preview );
    }

    private function is_google_maps_url( string $url ): bool {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

        return in_array( $host, [ 'maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'www.google.com' ], true )
            && ( false !== stripos( $url, 'maps' ) || false !== stripos( $url, 'goo.gl' ) );
    }

    private function get_google_maps_url_preview( array $segment, array $maps_details = [] ): array {
        $url = (string) ( $segment['url'] ?? '' );
        $location = trim( (string) ( $maps_details['address'] ?? $segment['location'] ?? '' ) );
        $title = trim( (string) ( $maps_details['title'] ?? $segment['title'] ?? '' ) ) ?: $location ?: __( 'Google Maps location', 'travel-app' );

        return [
            'title'       => sanitize_text_field( $title ),
            'description' => sanitize_text_field( $location && $location !== $title ? $location : '' ),
            'image'       => '',
            'site_name'   => 'Google Maps',
            'url'         => esc_url_raw( $url ),
        ];
    }

    private function get_google_maps_url_details( string $url ): array {
        $response = wp_safe_remote_head( $url, [
            'timeout'     => 5,
            'redirection' => 0,
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => str_replace( '_', '-', determine_locale() ) . ',en;q=0.8',
                'User-Agent'      => 'Mozilla/5.0 (compatible; TravelApp/1.0; +' . home_url( '/' ) . ')',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $location = wp_remote_retrieve_header( $response, 'location' );
        $location = is_array( $location ) ? reset( $location ) : $location;
        $location = (string) $location;
        if ( '' === $location ) {
            return [];
        }

        $path = (string) wp_parse_url( $location, PHP_URL_PATH );
        if ( ! preg_match( '#/maps/place/([^/]+)#', $path, $match ) ) {
            return [];
        }

        $address = rawurldecode( str_replace( '+', ' ', $match[1] ) );
        $parts = array_map( 'trim', explode( ',', $address ) );
        $title = sanitize_text_field( (string) ( $parts[0] ?? '' ) );
        $street_address = sanitize_text_field( implode( ', ', array_filter( array_slice( $parts, 1 ) ) ) );

        return [
            'title'   => $title,
            'address' => $street_address ?: sanitize_text_field( $address ),
        ];
    }

    private function fetch_url_preview( string $url, array &$debug = [] ): array {
        $debug = [
            'url'        => $url,
            'fetched_at' => current_time( 'mysql' ),
            'status'     => 'started',
            'message'    => '',
        ];

        if ( ! wp_http_validate_url( $url ) ) {
            $debug['status'] = 'invalid_url';
            $debug['message'] = __( 'The URL did not pass WordPress HTTP validation.', 'travel-app' );
            return [];
        }

        $response = wp_safe_remote_get( $url, [
            'timeout'             => 5,
            'redirection'         => 3,
            'limit_response_size' => 512 * 1024,
            'headers'             => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => str_replace( '_', '-', determine_locale() ) . ',en;q=0.8',
                'User-Agent'      => 'Mozilla/5.0 (compatible; TravelApp/1.0; +' . home_url( '/' ) . ')',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            $debug['status'] = 'request_error';
            $debug['message'] = $response->get_error_message();
            return [];
        }

        $response_code = (int) wp_remote_retrieve_response_code( $response );
        $debug['response_code'] = (string) $response_code;
        if ( $response_code < 200 || $response_code >= 300 ) {
            $debug['status'] = 'http_error';
            $debug['message'] = sprintf( __( 'Unexpected HTTP status %d.', 'travel-app' ), $response_code );
            return [];
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $content_type = is_array( $content_type ) ? implode( ',', $content_type ) : (string) $content_type;
        $debug['content_type'] = $content_type;
        if ( '' !== $content_type && false === stripos( $content_type, 'text/html' ) && false === stripos( $content_type, 'application/xhtml+xml' ) ) {
            $debug['status'] = 'unsupported_content_type';
            $debug['message'] = __( 'The URL did not return HTML content.', 'travel-app' );
            return [];
        }

        $body = (string) wp_remote_retrieve_body( $response );
        $debug['body_length'] = (string) strlen( $body );
        $debug['body_snippet'] = wp_strip_all_tags( substr( $body, 0, 500 ) );
        if ( '' === $body ) {
            $debug['status'] = 'empty_body';
            $debug['message'] = __( 'The URL returned an empty response body.', 'travel-app' );
            return [];
        }

        if ( false !== stripos( $body, 'awsWafCookieDomainList' ) || false !== stripos( $body, 'aws-waf-token' ) ) {
            $debug['status'] = 'blocked_by_waf';
            $debug['message'] = __( 'The URL returned an AWS WAF challenge instead of preview metadata.', 'travel-app' );
            return [];
        }

        if ( ! class_exists( '\DOMDocument' ) ) {
            $debug['status'] = 'missing_dom';
            $debug['message'] = __( 'The PHP DOM extension is unavailable.', 'travel-app' );
            return [];
        }

        $metadata = $this->extract_url_preview_metadata( $body, $url );
        $debug['metadata_source'] = $metadata['source'];
        $debug['has_title'] = '' !== $metadata['title'] ? 'yes' : 'no';
        $debug['has_description'] = '' !== $metadata['description'] ? 'yes' : 'no';
        $debug['has_image'] = '' !== $metadata['image'] ? 'yes' : 'no';
        $debug['site_name'] = $metadata['site_name'];
        if ( '' === $metadata['title'] && '' === $metadata['description'] && '' === $metadata['image'] ) {
            $debug['status'] = 'no_preview_fields';
            $debug['message'] = __( 'No title, description, or image metadata was found.', 'travel-app' );
            return [];
        }

        $debug['status'] = 'ok';
        $debug['message'] = __( 'Preview metadata was saved.', 'travel-app' );
        return $metadata;
    }

    private function extract_url_preview_metadata( string $html, string $url ): array {
        $previous_libxml_state = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous_libxml_state );

        $meta = [];
        foreach ( $document->getElementsByTagName( 'meta' ) as $node ) {
            $key = strtolower( trim( $node->getAttribute( 'property' ) ?: $node->getAttribute( 'name' ) ) );
            $content = trim( $node->getAttribute( 'content' ) );
            if ( '' !== $key && '' !== $content && ! isset( $meta[ $key ] ) ) {
                $meta[ $key ] = $content;
            }
        }

        $title = $meta['og:title'] ?? $meta['twitter:title'] ?? '';
        if ( '' === $title ) {
            $title_nodes = $document->getElementsByTagName( 'title' );
            $title = $title_nodes->length ? trim( (string) $title_nodes->item( 0 )->textContent ) : '';
        }

        $image = (string) ( $meta['og:image'] ?? $meta['twitter:image'] ?? '' );
        $json_ld = $this->extract_json_ld_preview_metadata( $document );

        return [
            'title'       => sanitize_text_field( $title ?: (string) ( $json_ld['title'] ?? '' ) ),
            'description' => sanitize_text_field( (string) ( $meta['og:description'] ?? $meta['description'] ?? $meta['twitter:description'] ?? $json_ld['description'] ?? '' ) ),
            'image'       => $this->resolve_preview_url( $image ?: (string) ( $json_ld['image'] ?? '' ), $url ),
            'site_name'   => sanitize_text_field( (string) ( $meta['og:site_name'] ?? wp_parse_url( $url, PHP_URL_HOST ) ?? '' ) ),
            'url'         => esc_url_raw( (string) ( $meta['og:url'] ?? $url ) ),
            'source'      => '' !== $title || '' !== $image || ! empty( $meta['og:description'] ) || ! empty( $meta['description'] ) || ! empty( $meta['twitter:description'] ) ? 'meta' : ( ! empty( $json_ld ) ? 'json_ld' : 'none' ),
        ];
    }

    private function extract_json_ld_preview_metadata( \DOMDocument $document ): array {
        foreach ( $document->getElementsByTagName( 'script' ) as $node ) {
            if ( 'application/ld+json' !== strtolower( trim( $node->getAttribute( 'type' ) ) ) ) {
                continue;
            }

            $data = json_decode( trim( (string) $node->textContent ), true );
            if ( ! is_array( $data ) ) {
                continue;
            }

            $metadata = $this->extract_json_ld_preview_metadata_from_value( $data );
            if ( ! empty( $metadata ) ) {
                return $metadata;
            }
        }

        return [];
    }

    private function extract_json_ld_preview_metadata_from_value( array $data ): array {
        if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
            foreach ( $data['@graph'] as $item ) {
                if ( is_array( $item ) ) {
                    $metadata = $this->extract_json_ld_preview_metadata_from_value( $item );
                    if ( ! empty( $metadata ) ) {
                        return $metadata;
                    }
                }
            }
        }

        if ( $this->is_list( $data ) ) {
            foreach ( $data as $item ) {
                if ( is_array( $item ) ) {
                    $metadata = $this->extract_json_ld_preview_metadata_from_value( $item );
                    if ( ! empty( $metadata ) ) {
                        return $metadata;
                    }
                }
            }
        }

        $title = (string) ( $data['name'] ?? $data['headline'] ?? '' );
        $description = (string) ( $data['description'] ?? '' );
        $image = $data['image'] ?? '';

        if ( is_array( $image ) ) {
            $image = $image['url'] ?? $image[0] ?? '';
        }

        if ( '' === $title && '' === $description && '' === (string) $image ) {
            return [];
        }

        return [
            'title'       => $title,
            'description' => $description,
            'image'       => (string) $image,
        ];
    }

    private function is_list( array $data ): bool {
        if ( [] === $data ) {
            return true;
        }

        return array_keys( $data ) === range( 0, count( $data ) - 1 );
    }

    private function resolve_preview_url( string $maybe_url, string $base_url ): string {
        $maybe_url = trim( $maybe_url );
        if ( '' === $maybe_url ) {
            return '';
        }

        if ( 0 === strpos( $maybe_url, '//' ) ) {
            $scheme = (string) wp_parse_url( $base_url, PHP_URL_SCHEME );
            $maybe_url = ( $scheme ?: 'https' ) . ':' . $maybe_url;
        } elseif ( 0 === strpos( $maybe_url, '/' ) ) {
            $scheme = (string) wp_parse_url( $base_url, PHP_URL_SCHEME );
            $host = (string) wp_parse_url( $base_url, PHP_URL_HOST );
            $port = wp_parse_url( $base_url, PHP_URL_PORT );
            $maybe_url = ( $scheme ?: 'https' ) . '://' . $host . ( $port ? ':' . $port : '' ) . $maybe_url;
        } elseif ( ! preg_match( '#^https?://#i', $maybe_url ) ) {
            $scheme = (string) wp_parse_url( $base_url, PHP_URL_SCHEME );
            $host = (string) wp_parse_url( $base_url, PHP_URL_HOST );
            $port = wp_parse_url( $base_url, PHP_URL_PORT );
            $path = (string) wp_parse_url( $base_url, PHP_URL_PATH );
            $directory = preg_replace( '#/[^/]*$#', '/', $path ) ?: '/';
            $maybe_url = ( $scheme ?: 'https' ) . '://' . $host . ( $port ? ':' . $port : '' ) . $directory . ltrim( $maybe_url, '/' );
        }

        return esc_url_raw( $maybe_url );
    }
}
