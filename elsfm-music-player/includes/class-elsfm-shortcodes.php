<?php
/**
 * ELSFM Shortcodes
 *
 * Registers all shortcodes for embedding music content.
 *
 * Available shortcodes:
 *   [elsfm_track id="123"]                      - Single track player
 *   [elsfm_album id="123"]                      - Album with tracklist
 *   [elsfm_artist id="123"]                     - Artist profile card
 *   [elsfm_playlist id="123"]                   - Playlist with tracklist
 *   [elsfm_search query="rock" types="track"]   - Search results
 *   [elsfm_tracks limit="12" orderBy="plays"]   - Track listing/grid
 *   [elsfm_albums limit="12"]                   - Album grid
 *   [elsfm_artists limit="12"]                  - Artist grid
 *   [elsfm_genre name="rock"]                   - Genre tracks
 *   [elsfm_radio type="artist" id="1"]          - Radio / recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ELSFM_Shortcodes {

    private static $instance = null;
    private $api;
    private $assets_enqueued = false;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api = new ELSFM_API_Client();

        add_shortcode( 'elsfm_track', array( $this, 'render_track' ) );
        add_shortcode( 'elsfm_album', array( $this, 'render_album' ) );
        add_shortcode( 'elsfm_artist', array( $this, 'render_artist' ) );
        add_shortcode( 'elsfm_playlist', array( $this, 'render_playlist' ) );
        add_shortcode( 'elsfm_search', array( $this, 'render_search' ) );
        add_shortcode( 'elsfm_tracks', array( $this, 'render_tracks' ) );
        add_shortcode( 'elsfm_albums', array( $this, 'render_albums' ) );
        add_shortcode( 'elsfm_artists', array( $this, 'render_artists' ) );
        add_shortcode( 'elsfm_genre', array( $this, 'render_genre' ) );
        add_shortcode( 'elsfm_radio', array( $this, 'render_radio' ) );
    }

    /**
     * Ensure frontend CSS/JS are loaded when a shortcode is used.
     */
    private function enqueue_assets() {
        if ( $this->assets_enqueued ) {
            return;
        }
        wp_enqueue_style( 'elsfm-player' );
        wp_enqueue_script( 'elsfm-player' );
        $this->assets_enqueued = true;
    }

    /**
     * Format milliseconds to M:SS.
     */
    private function format_duration( $ms ) {
        if ( ! $ms || ! is_numeric( $ms ) ) {
            return '0:00';
        }
        $seconds = intval( $ms / 1000 );
        $m       = floor( $seconds / 60 );
        $s       = $seconds % 60;
        return sprintf( '%d:%02d', $m, $s );
    }

    /**
     * Resolve image URL — handles relative/absolute paths.
     */
    private function img_url( $image ) {
        if ( empty( $image ) ) {
            return '';
        }
        if ( 0 === strpos( $image, 'http' ) ) {
            return $image;
        }
        return rtrim( get_option( 'elsfm_api_url', 'https://elsfm.com' ), '/' ) . '/' . ltrim( $image, '/' );
    }

    /**
     * Get artist names as comma-separated string.
     */
    private function artist_names( $artists ) {
        if ( empty( $artists ) || ! is_array( $artists ) ) {
            return __( 'Unknown Artist', 'elsfm-music-player' );
        }
        return implode( ', ', array_column( $artists, 'name' ) );
    }

    /**
     * Render error message.
     */
    private function render_error( $message ) {
        return '<div class="elsfm-error">' . esc_html( $message ) . '</div>';
    }

    /**
     * Build a track row HTML for tracklists.
     */
    private function track_row( $track, $index = 0 ) {
        $id       = isset( $track['id'] ) ? intval( $track['id'] ) : 0;
        $name     = isset( $track['name'] ) ? esc_html( $track['name'] ) : '';
        $duration = isset( $track['duration'] ) ? $this->format_duration( $track['duration'] ) : '0:00';
        $image    = $this->img_url( isset( $track['image'] ) ? $track['image'] : '' );
        $artists  = $this->artist_names( isset( $track['artists'] ) ? $track['artists'] : array() );
        $plays    = isset( $track['plays'] ) ? number_format( intval( $track['plays'] ) ) : '0';

        $data_attrs = sprintf(
            'data-track-id="%d" data-track-name="%s" data-track-artist="%s" data-track-image="%s" data-track-duration="%s"',
            $id,
            esc_attr( isset( $track['name'] ) ? $track['name'] : '' ),
            esc_attr( $artists ),
            esc_attr( $image ),
            esc_attr( isset( $track['duration'] ) ? $track['duration'] : 0 )
        );

        $img_html = '';
        if ( $image ) {
            $img_html = '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" class="elsfm-track-thumb" loading="lazy" />';
        }

        return sprintf(
            '<div class="elsfm-track-row" %s>
                <div class="elsfm-track-num">%d</div>
                <div class="elsfm-track-img">%s</div>
                <div class="elsfm-track-info">
                    <span class="elsfm-track-name">%s</span>
                    <span class="elsfm-track-artist">%s</span>
                </div>
                <div class="elsfm-track-plays">%s</div>
                <div class="elsfm-track-duration">%s</div>
                <button class="elsfm-play-btn" aria-label="%s" title="%s">
                    <svg viewBox="0 0 24 24" width="20" height="20"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                </button>
            </div>',
            $data_attrs,
            $index + 1,
            $img_html,
            $name,
            esc_html( $artists ),
            $plays,
            $duration,
            esc_attr__( 'Play', 'elsfm-music-player' ),
            esc_attr__( 'Play', 'elsfm-music-player' )
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_track id="123"]
    // -------------------------------------------------------------------------
    public function render_track( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'elsfm_track' );

        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return $this->render_error( __( 'Track ID is required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get_track( $id );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $track = isset( $response['track'] ) ? $response['track'] : $response;
        if ( empty( $track['id'] ) ) {
            return $this->render_error( __( 'Track not found.', 'elsfm-music-player' ) );
        }

        $name     = esc_html( $track['name'] );
        $image    = $this->img_url( isset( $track['image'] ) ? $track['image'] : '' );
        $artists  = $this->artist_names( isset( $track['artists'] ) ? $track['artists'] : array() );
        $album    = isset( $track['album']['name'] ) ? esc_html( $track['album']['name'] ) : '';
        $duration = $this->format_duration( isset( $track['duration'] ) ? $track['duration'] : 0 );

        $data_attrs = sprintf(
            'data-track-id="%d" data-track-name="%s" data-track-artist="%s" data-track-image="%s" data-track-duration="%s"',
            intval( $track['id'] ),
            esc_attr( $track['name'] ),
            esc_attr( $artists ),
            esc_attr( $image ),
            esc_attr( isset( $track['duration'] ) ? $track['duration'] : 0 )
        );

        $img_html = '';
        if ( $image ) {
            $img_html = '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
        } else {
            $img_html = '<div class="elsfm-no-image"><svg viewBox="0 0 24 24" width="48" height="48"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z" fill="currentColor"/></svg></div>';
        }

        return sprintf(
            '<div class="elsfm-single-track" %s>
                <div class="elsfm-track-artwork">%s</div>
                <div class="elsfm-track-details">
                    <h3 class="elsfm-track-title">%s</h3>
                    <p class="elsfm-track-meta">%s</p>
                    %s
                    <div class="elsfm-track-controls">
                        <button class="elsfm-play-btn elsfm-play-btn--large" aria-label="%s">
                            <svg viewBox="0 0 24 24" width="28" height="28"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                        </button>
                        <span class="elsfm-track-time">%s</span>
                    </div>
                </div>
            </div>',
            $data_attrs,
            $img_html,
            $name,
            esc_html( $artists ),
            $album ? '<p class="elsfm-track-album">' . $album . '</p>' : '',
            esc_attr__( 'Play', 'elsfm-music-player' ),
            $duration
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_album id="123"]
    // -------------------------------------------------------------------------
    public function render_album( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'elsfm_album' );

        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return $this->render_error( __( 'Album ID is required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get_album( $id );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $album = isset( $response['album'] ) ? $response['album'] : $response;
        if ( empty( $album['id'] ) ) {
            return $this->render_error( __( 'Album not found.', 'elsfm-music-player' ) );
        }

        $name    = esc_html( $album['name'] );
        $image   = $this->img_url( isset( $album['image'] ) ? $album['image'] : '' );
        $artists = $this->artist_names( isset( $album['artists'] ) ? $album['artists'] : array() );
        $year    = ! empty( $album['release_date'] ) ? date( 'Y', strtotime( $album['release_date'] ) ) : '';

        $tracks_html = '';
        $tracks_data = isset( $album['tracks'] ) ? $album['tracks'] : array();
        // tracks might be in pagination format
        if ( isset( $tracks_data['data'] ) ) {
            $tracks_data = $tracks_data['data'];
        }
        if ( is_array( $tracks_data ) ) {
            foreach ( $tracks_data as $i => $track ) {
                $tracks_html .= $this->track_row( $track, $i );
            }
        }

        $img_html = $image
            ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />'
            : '<div class="elsfm-no-image"><svg viewBox="0 0 24 24" width="64" height="64"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z" fill="currentColor"/></svg></div>';

        return sprintf(
            '<div class="elsfm-album" data-album-id="%d">
                <div class="elsfm-album-header">
                    <div class="elsfm-album-artwork">%s</div>
                    <div class="elsfm-album-info">
                        <span class="elsfm-label">%s</span>
                        <h3 class="elsfm-album-title">%s</h3>
                        <p class="elsfm-album-artist">%s</p>
                        %s
                    </div>
                </div>
                <div class="elsfm-tracklist">%s</div>
            </div>',
            intval( $album['id'] ),
            $img_html,
            esc_html__( 'ALBUM', 'elsfm-music-player' ),
            $name,
            esc_html( $artists ),
            $year ? '<span class="elsfm-album-year">' . esc_html( $year ) . '</span>' : '',
            $tracks_html
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_artist id="123"]
    // -------------------------------------------------------------------------
    public function render_artist( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'elsfm_artist' );

        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return $this->render_error( __( 'Artist ID is required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get_artist( $id );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $artist = isset( $response['artist'] ) ? $response['artist'] : $response;
        if ( empty( $artist['id'] ) ) {
            return $this->render_error( __( 'Artist not found.', 'elsfm-music-player' ) );
        }

        $name       = esc_html( $artist['name'] );
        $image      = $this->img_url( isset( $artist['image_small'] ) ? $artist['image_small'] : '' );
        $verified   = ! empty( $artist['verified'] );
        $followers  = isset( $artist['followers_count'] ) ? number_format( intval( $artist['followers_count'] ) ) : '0';
        $bio        = isset( $artist['profile']['description'] ) ? wp_kses_post( $artist['profile']['description'] ) : '';

        // Top tracks
        $top_tracks_html = '';
        $top_tracks      = isset( $artist['top_tracks'] ) ? $artist['top_tracks'] : array();
        if ( ! empty( $top_tracks ) && is_array( $top_tracks ) ) {
            $top_tracks_html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Popular Tracks', 'elsfm-music-player' ) . '</h4><div class="elsfm-tracklist">';
            foreach ( array_slice( $top_tracks, 0, 5 ) as $i => $track ) {
                $top_tracks_html .= $this->track_row( $track, $i );
            }
            $top_tracks_html .= '</div></div>';
        }

        // Albums
        $albums_html = '';
        $albums      = isset( $artist['albums'] ) ? $artist['albums'] : array();
        if ( isset( $albums['data'] ) ) {
            $albums = $albums['data'];
        }
        if ( ! empty( $albums ) && is_array( $albums ) ) {
            $albums_html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Albums', 'elsfm-music-player' ) . '</h4><div class="elsfm-grid">';
            foreach ( array_slice( $albums, 0, 8 ) as $album ) {
                $aimg  = $this->img_url( isset( $album['image'] ) ? $album['image'] : '' );
                $aname = esc_html( isset( $album['name'] ) ? $album['name'] : '' );
                $albums_html .= sprintf(
                    '<div class="elsfm-grid-item" data-album-id="%d">
                        <div class="elsfm-grid-img">%s</div>
                        <p class="elsfm-grid-title">%s</p>
                    </div>',
                    intval( $album['id'] ),
                    $aimg ? '<img src="' . esc_url( $aimg ) . '" alt="' . esc_attr( $aname ) . '" loading="lazy" />' : '',
                    $aname
                );
            }
            $albums_html .= '</div></div>';
        }

        $verified_badge = $verified
            ? ' <svg class="elsfm-verified" viewBox="0 0 24 24" width="18" height="18"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" fill="currentColor"/></svg>'
            : '';

        return sprintf(
            '<div class="elsfm-artist" data-artist-id="%d">
                <div class="elsfm-artist-header">
                    <div class="elsfm-artist-avatar">%s</div>
                    <div class="elsfm-artist-info">
                        <h3 class="elsfm-artist-name">%s%s</h3>
                        <p class="elsfm-artist-followers">%s %s</p>
                        %s
                    </div>
                </div>
                %s
                %s
            </div>',
            intval( $artist['id'] ),
            $image ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />' : '',
            $name,
            $verified_badge,
            $followers,
            esc_html__( 'followers', 'elsfm-music-player' ),
            $bio ? '<p class="elsfm-artist-bio">' . $bio . '</p>' : '',
            $top_tracks_html,
            $albums_html
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_playlist id="123"]
    // -------------------------------------------------------------------------
    public function render_playlist( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'elsfm_playlist' );

        $id = intval( $atts['id'] );
        if ( ! $id ) {
            return $this->render_error( __( 'Playlist ID is required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get_playlist( $id );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $playlist = isset( $response['playlist'] ) ? $response['playlist'] : $response;
        if ( empty( $playlist['id'] ) ) {
            return $this->render_error( __( 'Playlist not found.', 'elsfm-music-player' ) );
        }

        $name  = esc_html( $playlist['name'] );
        $desc  = isset( $playlist['description'] ) ? esc_html( $playlist['description'] ) : '';
        $image = $this->img_url( isset( $playlist['image'] ) ? $playlist['image'] : '' );
        $total = isset( $playlist['totalDuration'] ) ? $this->format_duration( $playlist['totalDuration'] ) : '';

        $tracks_html = '';
        $tracks_data = isset( $playlist['tracks'] ) ? $playlist['tracks'] : array();
        if ( isset( $tracks_data['data'] ) ) {
            $tracks_data = $tracks_data['data'];
        }
        if ( is_array( $tracks_data ) ) {
            foreach ( $tracks_data as $i => $track ) {
                $tracks_html .= $this->track_row( $track, $i );
            }
        }

        $track_count = is_array( $tracks_data ) ? count( $tracks_data ) : 0;

        $img_html = $image
            ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" />'
            : '<div class="elsfm-no-image"><svg viewBox="0 0 24 24" width="64" height="64"><path d="M15 6H3v2h12V6zm0 4H3v2h12v-2zM3 16h8v-2H3v2zM17 6v8.18c-.31-.11-.65-.18-1-.18-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3V8h3V6h-5z" fill="currentColor"/></svg></div>';

        return sprintf(
            '<div class="elsfm-playlist" data-playlist-id="%d">
                <div class="elsfm-playlist-header">
                    <div class="elsfm-playlist-artwork">%s</div>
                    <div class="elsfm-playlist-info">
                        <span class="elsfm-label">%s</span>
                        <h3 class="elsfm-playlist-title">%s</h3>
                        %s
                        <p class="elsfm-playlist-meta">%d %s %s</p>
                    </div>
                </div>
                <div class="elsfm-tracklist">%s</div>
            </div>',
            intval( $playlist['id'] ),
            $img_html,
            esc_html__( 'PLAYLIST', 'elsfm-music-player' ),
            $name,
            $desc ? '<p class="elsfm-playlist-desc">' . $desc . '</p>' : '',
            $track_count,
            esc_html__( 'tracks', 'elsfm-music-player' ),
            $total ? '&middot; ' . esc_html( $total ) : '',
            $tracks_html
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_search query="rock" types="track,album" limit="10"]
    // -------------------------------------------------------------------------
    public function render_search( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'query' => '',
            'types' => '',
            'limit' => 10,
        ), $atts, 'elsfm_search' );

        // If no query, render a search form
        if ( empty( $atts['query'] ) ) {
            return '<div class="elsfm-search-form">
                <form method="get" class="elsfm-search" data-elsfm-search>
                    <input type="text" name="elsfm_q" placeholder="' . esc_attr__( 'Search music...', 'elsfm-music-player' ) . '" class="elsfm-search-input" />
                    <button type="submit" class="elsfm-search-btn">' . esc_html__( 'Search', 'elsfm-music-player' ) . '</button>
                </form>
                <div class="elsfm-search-results" data-elsfm-search-results></div>
            </div>';
        }

        $response = $this->api->search( $atts['query'], $atts['types'], intval( $atts['limit'] ) );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $results = isset( $response['results'] ) ? $response['results'] : array();
        $html    = '<div class="elsfm-search-results-static">';

        // Tracks
        if ( ! empty( $results['tracks']['data'] ) ) {
            $html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Tracks', 'elsfm-music-player' ) . '</h4><div class="elsfm-tracklist">';
            foreach ( $results['tracks']['data'] as $i => $track ) {
                $html .= $this->track_row( $track, $i );
            }
            $html .= '</div></div>';
        }

        // Albums
        if ( ! empty( $results['albums']['data'] ) ) {
            $html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Albums', 'elsfm-music-player' ) . '</h4><div class="elsfm-grid">';
            foreach ( $results['albums']['data'] as $album ) {
                $aimg  = $this->img_url( isset( $album['image'] ) ? $album['image'] : '' );
                $aname = esc_html( isset( $album['name'] ) ? $album['name'] : '' );
                $html .= sprintf(
                    '<div class="elsfm-grid-item"><div class="elsfm-grid-img">%s</div><p class="elsfm-grid-title">%s</p></div>',
                    $aimg ? '<img src="' . esc_url( $aimg ) . '" alt="' . esc_attr( $aname ) . '" loading="lazy" />' : '',
                    $aname
                );
            }
            $html .= '</div></div>';
        }

        // Artists
        if ( ! empty( $results['artists']['data'] ) ) {
            $html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Artists', 'elsfm-music-player' ) . '</h4><div class="elsfm-grid elsfm-grid--round">';
            foreach ( $results['artists']['data'] as $artist ) {
                $aimg  = $this->img_url( isset( $artist['image_small'] ) ? $artist['image_small'] : '' );
                $aname = esc_html( isset( $artist['name'] ) ? $artist['name'] : '' );
                $html .= sprintf(
                    '<div class="elsfm-grid-item"><div class="elsfm-grid-img">%s</div><p class="elsfm-grid-title">%s</p></div>',
                    $aimg ? '<img src="' . esc_url( $aimg ) . '" alt="' . esc_attr( $aname ) . '" loading="lazy" />' : '',
                    $aname
                );
            }
            $html .= '</div></div>';
        }

        // Playlists
        if ( ! empty( $results['playlists']['data'] ) ) {
            $html .= '<div class="elsfm-section"><h4>' . esc_html__( 'Playlists', 'elsfm-music-player' ) . '</h4><div class="elsfm-grid">';
            foreach ( $results['playlists']['data'] as $pl ) {
                $pimg  = $this->img_url( isset( $pl['image'] ) ? $pl['image'] : '' );
                $pname = esc_html( isset( $pl['name'] ) ? $pl['name'] : '' );
                $html .= sprintf(
                    '<div class="elsfm-grid-item"><div class="elsfm-grid-img">%s</div><p class="elsfm-grid-title">%s</p></div>',
                    $pimg ? '<img src="' . esc_url( $pimg ) . '" alt="' . esc_attr( $pname ) . '" loading="lazy" />' : '',
                    $pname
                );
            }
            $html .= '</div></div>';
        }

        if ( '<div class="elsfm-search-results-static">' === $html ) {
            $html .= '<p class="elsfm-no-results">' . esc_html__( 'No results found.', 'elsfm-music-player' ) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_tracks limit="12" orderBy="plays" orderDir="desc"]
    // -------------------------------------------------------------------------
    public function render_tracks( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'limit'    => get_option( 'elsfm_per_page', 12 ),
            'orderBy'  => 'created_at',
            'orderDir' => 'desc',
            'layout'   => 'list',
        ), $atts, 'elsfm_tracks' );

        $response = $this->api->get_tracks( array(
            'perPage'  => intval( $atts['limit'] ),
            'orderBy'  => sanitize_text_field( $atts['orderBy'] ),
            'orderDir' => sanitize_text_field( $atts['orderDir'] ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $tracks = array();
        if ( isset( $response['pagination']['data'] ) ) {
            $tracks = $response['pagination']['data'];
        } elseif ( isset( $response['data'] ) ) {
            $tracks = $response['data'];
        }

        if ( empty( $tracks ) ) {
            return $this->render_error( __( 'No tracks found.', 'elsfm-music-player' ) );
        }

        if ( 'grid' === $atts['layout'] ) {
            $html = '<div class="elsfm-grid">';
            foreach ( $tracks as $track ) {
                $timg  = $this->img_url( isset( $track['image'] ) ? $track['image'] : '' );
                $tname = esc_html( isset( $track['name'] ) ? $track['name'] : '' );
                $tart  = $this->artist_names( isset( $track['artists'] ) ? $track['artists'] : array() );
                $data  = sprintf(
                    'data-track-id="%d" data-track-name="%s" data-track-artist="%s" data-track-image="%s" data-track-duration="%s"',
                    intval( $track['id'] ),
                    esc_attr( $track['name'] ),
                    esc_attr( $tart ),
                    esc_attr( $timg ),
                    esc_attr( isset( $track['duration'] ) ? $track['duration'] : 0 )
                );
                $html .= sprintf(
                    '<div class="elsfm-grid-item elsfm-grid-item--playable" %s>
                        <div class="elsfm-grid-img">%s<button class="elsfm-play-overlay" aria-label="%s"><svg viewBox="0 0 24 24" width="36" height="36"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg></button></div>
                        <p class="elsfm-grid-title">%s</p>
                        <p class="elsfm-grid-subtitle">%s</p>
                    </div>',
                    $data,
                    $timg ? '<img src="' . esc_url( $timg ) . '" alt="' . esc_attr( $tname ) . '" loading="lazy" />' : '',
                    esc_attr__( 'Play', 'elsfm-music-player' ),
                    $tname,
                    esc_html( $tart )
                );
            }
            $html .= '</div>';
            return $html;
        }

        // List layout
        $html = '<div class="elsfm-tracklist">';
        foreach ( $tracks as $i => $track ) {
            $html .= $this->track_row( $track, $i );
        }
        $html .= '</div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_albums limit="12" orderBy="created_at"]
    // -------------------------------------------------------------------------
    public function render_albums( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'limit'    => get_option( 'elsfm_per_page', 12 ),
            'orderBy'  => 'created_at',
            'orderDir' => 'desc',
        ), $atts, 'elsfm_albums' );

        $response = $this->api->get_albums( array(
            'perPage'  => intval( $atts['limit'] ),
            'orderBy'  => sanitize_text_field( $atts['orderBy'] ),
            'orderDir' => sanitize_text_field( $atts['orderDir'] ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $albums = array();
        if ( isset( $response['pagination']['data'] ) ) {
            $albums = $response['pagination']['data'];
        } elseif ( isset( $response['data'] ) ) {
            $albums = $response['data'];
        }

        if ( empty( $albums ) ) {
            return $this->render_error( __( 'No albums found.', 'elsfm-music-player' ) );
        }

        $html = '<div class="elsfm-grid">';
        foreach ( $albums as $album ) {
            $aimg    = $this->img_url( isset( $album['image'] ) ? $album['image'] : '' );
            $aname   = esc_html( isset( $album['name'] ) ? $album['name'] : '' );
            $artists = $this->artist_names( isset( $album['artists'] ) ? $album['artists'] : array() );
            $html   .= sprintf(
                '<div class="elsfm-grid-item" data-album-id="%d">
                    <div class="elsfm-grid-img">%s</div>
                    <p class="elsfm-grid-title">%s</p>
                    <p class="elsfm-grid-subtitle">%s</p>
                </div>',
                intval( $album['id'] ),
                $aimg ? '<img src="' . esc_url( $aimg ) . '" alt="' . esc_attr( $aname ) . '" loading="lazy" />' : '',
                $aname,
                esc_html( $artists )
            );
        }
        $html .= '</div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_artists limit="12"]
    // -------------------------------------------------------------------------
    public function render_artists( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'limit'    => get_option( 'elsfm_per_page', 12 ),
            'orderBy'  => 'created_at',
            'orderDir' => 'desc',
        ), $atts, 'elsfm_artists' );

        $response = $this->api->get_artists( array(
            'perPage'  => intval( $atts['limit'] ),
            'orderBy'  => sanitize_text_field( $atts['orderBy'] ),
            'orderDir' => sanitize_text_field( $atts['orderDir'] ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $artists = array();
        if ( isset( $response['pagination']['data'] ) ) {
            $artists = $response['pagination']['data'];
        } elseif ( isset( $response['data'] ) ) {
            $artists = $response['data'];
        }

        if ( empty( $artists ) ) {
            return $this->render_error( __( 'No artists found.', 'elsfm-music-player' ) );
        }

        $html = '<div class="elsfm-grid elsfm-grid--round">';
        foreach ( $artists as $artist ) {
            $aimg  = $this->img_url( isset( $artist['image_small'] ) ? $artist['image_small'] : '' );
            $aname = esc_html( isset( $artist['name'] ) ? $artist['name'] : '' );
            $html .= sprintf(
                '<div class="elsfm-grid-item" data-artist-id="%d">
                    <div class="elsfm-grid-img">%s</div>
                    <p class="elsfm-grid-title">%s</p>
                </div>',
                intval( $artist['id'] ),
                $aimg ? '<img src="' . esc_url( $aimg ) . '" alt="' . esc_attr( $aname ) . '" loading="lazy" />' : '',
                $aname
            );
        }
        $html .= '</div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_genre name="rock" limit="20"]
    // -------------------------------------------------------------------------
    public function render_genre( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'name'  => '',
            'limit' => get_option( 'elsfm_per_page', 12 ),
        ), $atts, 'elsfm_genre' );

        if ( empty( $atts['name'] ) ) {
            return $this->render_error( __( 'Genre name is required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get( 'tags/' . sanitize_title( $atts['name'] ) . '/tracks', array(
            'perPage' => intval( $atts['limit'] ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $tracks = array();
        if ( isset( $response['pagination']['data'] ) ) {
            $tracks = $response['pagination']['data'];
        } elseif ( isset( $response['data'] ) ) {
            $tracks = $response['data'];
        }

        if ( empty( $tracks ) ) {
            return $this->render_error( __( 'No tracks found for this genre.', 'elsfm-music-player' ) );
        }

        $html = '<div class="elsfm-genre">';
        $html .= '<h4 class="elsfm-genre-title">' . esc_html( ucfirst( $atts['name'] ) ) . '</h4>';
        $html .= '<div class="elsfm-tracklist">';
        foreach ( $tracks as $i => $track ) {
            $html .= $this->track_row( $track, $i );
        }
        $html .= '</div></div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Shortcode: [elsfm_radio type="artist" id="1"]
    // -------------------------------------------------------------------------
    public function render_radio( $atts ) {
        $this->enqueue_assets();

        $atts = shortcode_atts( array(
            'type' => 'artist',
            'id'   => 0,
        ), $atts, 'elsfm_radio' );

        $type = sanitize_text_field( $atts['type'] );
        $id   = intval( $atts['id'] );

        if ( ! $id || ! in_array( $type, array( 'artist', 'track', 'genre' ), true ) ) {
            return $this->render_error( __( 'Valid type (artist/track/genre) and ID are required.', 'elsfm-music-player' ) );
        }

        $response = $this->api->get_radio( $type, $id );
        if ( is_wp_error( $response ) ) {
            return $this->render_error( $response->get_error_message() );
        }

        $recommendations = isset( $response['recommendations'] ) ? $response['recommendations'] : array();
        $seed_name       = '';
        if ( isset( $response['seed']['name'] ) ) {
            $seed_name = esc_html( $response['seed']['name'] );
        }

        if ( empty( $recommendations ) ) {
            return $this->render_error( __( 'No recommendations available.', 'elsfm-music-player' ) );
        }

        $html = '<div class="elsfm-radio">';
        if ( $seed_name ) {
            $html .= '<h4 class="elsfm-radio-title">' . sprintf(
                /* translators: %s: seed name (artist, track, or genre) */
                esc_html__( 'Radio: %s', 'elsfm-music-player' ),
                $seed_name
            ) . '</h4>';
        }
        $html .= '<div class="elsfm-tracklist">';
        foreach ( $recommendations as $i => $track ) {
            $html .= $this->track_row( $track, $i );
        }
        $html .= '</div></div>';
        return $html;
    }
}
