<?php
/**
 * ELSFM API Client
 *
 * Handles all HTTP communication with the ELSFM (BeMusic) REST API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ELSFM_API_Client {

    private $base_url;
    private $token;
    private $timeout;

    public function __construct() {
        $this->base_url = rtrim( get_option( 'elsfm_api_url', 'https://elsfm.com' ), '/' ) . '/api/v1';
        $this->token    = get_option( 'elsfm_api_token', '' );
        $this->timeout  = (int) get_option( 'elsfm_api_timeout', 15 );
    }

    /**
     * Build request headers.
     */
    private function headers() {
        $headers = array(
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        );

        if ( ! empty( $this->token ) ) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * Perform a GET request.
     *
     * @param string $endpoint API endpoint (e.g. "tracks" or "albums/5").
     * @param array  $params   Query parameters.
     * @return array|WP_Error  Decoded JSON response or WP_Error.
     */
    public function get( $endpoint, $params = array() ) {
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }

        $cache_key = 'elsfm_' . md5( $url );
        $cache_ttl = (int) get_option( 'elsfm_cache_ttl', 300 );

        if ( $cache_ttl > 0 ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $response = wp_remote_get( $url, array(
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
        ) );

        return $this->handle_response( $response, $cache_key, $cache_ttl );
    }

    /**
     * Perform a POST request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $body     Request body data.
     * @return array|WP_Error  Decoded JSON response or WP_Error.
     */
    public function post( $endpoint, $body = array() ) {
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        $response = wp_remote_post( $url, array(
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
            'body'    => wp_json_encode( $body ),
        ) );

        return $this->handle_response( $response );
    }

    /**
     * Process the HTTP response.
     *
     * @param array|WP_Error $response  HTTP response.
     * @param string         $cache_key Optional transient key.
     * @param int            $cache_ttl Optional cache duration in seconds.
     * @return array|WP_Error
     */
    private function handle_response( $response, $cache_key = '', $cache_ttl = 0 ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = isset( $data['message'] ) ? $data['message'] : "API returned HTTP {$code}";
            return new WP_Error( 'elsfm_api_error', $message, array( 'status' => $code ) );
        }

        if ( ! empty( $cache_key ) && $cache_ttl > 0 && is_array( $data ) ) {
            set_transient( $cache_key, $data, $cache_ttl );
        }

        return $data;
    }

    /**
     * Helper: get a single track.
     */
    public function get_track( $id, $with = 'album,artists,genres,tags' ) {
        return $this->get( "tracks/{$id}", array( 'with' => $with ) );
    }

    /**
     * Helper: get tracks list.
     */
    public function get_tracks( $params = array() ) {
        $defaults = array( 'perPage' => 20, 'page' => 1 );
        return $this->get( 'tracks', wp_parse_args( $params, $defaults ) );
    }

    /**
     * Helper: get a single album with tracks.
     */
    public function get_album( $id, $with = 'artists,tracks,genres,tags' ) {
        return $this->get( "albums/{$id}", array( 'with' => $with ) );
    }

    /**
     * Helper: get albums list.
     */
    public function get_albums( $params = array() ) {
        $defaults = array( 'perPage' => 20, 'page' => 1 );
        return $this->get( 'albums', wp_parse_args( $params, $defaults ) );
    }

    /**
     * Helper: get a single artist.
     */
    public function get_artist( $id, $with = 'albums,genres,topTracks' ) {
        return $this->get( "artists/{$id}", array( 'with' => $with ) );
    }

    /**
     * Helper: get artists list.
     */
    public function get_artists( $params = array() ) {
        $defaults = array( 'perPage' => 20, 'page' => 1 );
        return $this->get( 'artists', wp_parse_args( $params, $defaults ) );
    }

    /**
     * Helper: get a single playlist with tracks.
     */
    public function get_playlist( $id ) {
        return $this->get( "playlists/{$id}" );
    }

    /**
     * Helper: get playlists list.
     */
    public function get_playlists( $params = array() ) {
        $defaults = array( 'perPage' => 20, 'page' => 1 );
        return $this->get( 'playlists', wp_parse_args( $params, $defaults ) );
    }

    /**
     * Helper: search across all types.
     */
    public function search( $query, $types = '', $limit = 10 ) {
        $params = array( 'query' => $query, 'limit' => $limit );
        if ( ! empty( $types ) ) {
            $params['types'] = $types;
        }
        return $this->get( 'search', $params );
    }

    /**
     * Helper: get genres.
     */
    public function get_genres( $params = array() ) {
        $defaults = array( 'perPage' => 50, 'page' => 1 );
        return $this->get( 'genres', wp_parse_args( $params, $defaults ) );
    }

    /**
     * Helper: get a channel.
     */
    public function get_channel( $id ) {
        return $this->get( "channel/{$id}" );
    }

    /**
     * Helper: get radio recommendations.
     */
    public function get_radio( $type, $id ) {
        return $this->get( "radio/{$type}/{$id}" );
    }
}
