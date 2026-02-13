<?php
/**
 * Plugin Name: ELSFM Music Player
 * Plugin URI: https://elsfm.com
 * Description: Embed music from your ELSFM (BeMusic) platform into WordPress pages and posts using shortcodes. Stream tracks, albums, playlists, and artist profiles with a built-in audio player.
 * Version: 1.0.0
 * Author: ELSFM
 * Author URI: https://elsfm.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: elsfm-music-player
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ELSFM_VERSION', '1.0.0' );
define( 'ELSFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELSFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELSFM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ELSFM_PLUGIN_DIR . 'includes/class-elsfm-api-client.php';
require_once ELSFM_PLUGIN_DIR . 'includes/class-elsfm-admin.php';
require_once ELSFM_PLUGIN_DIR . 'includes/class-elsfm-shortcodes.php';

/**
 * Main plugin class.
 */
final class ELSFM_Music_Player {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_filter( 'plugin_action_links_' . ELSFM_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

        ELSFM_Admin::instance();
        ELSFM_Shortcodes::instance();
    }

    public function init() {
        load_plugin_textdomain( 'elsfm-music-player', false, dirname( ELSFM_PLUGIN_BASENAME ) . '/languages' );
    }

    public function enqueue_frontend_assets() {
        wp_register_style(
            'elsfm-player',
            ELSFM_PLUGIN_URL . 'assets/css/elsfm-player.css',
            array(),
            ELSFM_VERSION
        );

        wp_register_script(
            'elsfm-player',
            ELSFM_PLUGIN_URL . 'assets/js/elsfm-player.js',
            array(),
            ELSFM_VERSION,
            true
        );

        wp_localize_script( 'elsfm-player', 'elsfmConfig', array(
            'apiUrl'  => rtrim( get_option( 'elsfm_api_url', 'https://elsfm.com' ), '/' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'elsfm_nonce' ),
        ) );

        // Inject accent color as CSS custom property
        $accent = get_option( 'elsfm_accent_color', '#1db954' );
        if ( $accent ) {
            wp_add_inline_style( 'elsfm-player', ':root { --elsfm-accent: ' . sanitize_hex_color( $accent ) . '; }' );
        }
    }

    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=elsfm-settings' ) . '">'
            . esc_html__( 'Settings', 'elsfm-music-player' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

/**
 * AJAX handler to proxy API requests (keeps token server-side).
 */
add_action( 'wp_ajax_elsfm_api_proxy', 'elsfm_ajax_api_proxy' );
add_action( 'wp_ajax_nopriv_elsfm_api_proxy', 'elsfm_ajax_api_proxy' );

function elsfm_ajax_api_proxy() {
    check_ajax_referer( 'elsfm_nonce', 'nonce' );

    $endpoint = isset( $_GET['endpoint'] ) ? sanitize_text_field( wp_unslash( $_GET['endpoint'] ) ) : '';
    if ( empty( $endpoint ) ) {
        wp_send_json_error( 'Missing endpoint' );
    }

    $allowed_prefixes = array(
        'tracks', 'albums', 'artists', 'playlists',
        'search', 'genres', 'channel', 'radio',
        'tags', 'player',
    );

    $first_segment = explode( '/', ltrim( $endpoint, '/' ) )[0];
    if ( ! in_array( $first_segment, $allowed_prefixes, true ) ) {
        wp_send_json_error( 'Endpoint not allowed' );
    }

    $params = isset( $_GET['params'] ) ? json_decode( sanitize_text_field( wp_unslash( $_GET['params'] ) ), true ) : array();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    $client   = new ELSFM_API_Client();
    $response = $client->get( $endpoint, $params );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    wp_send_json_success( $response );
}

/**
 * AJAX handler for POST-based API proxy (player queue, etc.).
 */
add_action( 'wp_ajax_elsfm_api_proxy_post', 'elsfm_ajax_api_proxy_post' );
add_action( 'wp_ajax_nopriv_elsfm_api_proxy_post', 'elsfm_ajax_api_proxy_post' );

function elsfm_ajax_api_proxy_post() {
    check_ajax_referer( 'elsfm_nonce', 'nonce' );

    $endpoint = isset( $_POST['endpoint'] ) ? sanitize_text_field( wp_unslash( $_POST['endpoint'] ) ) : '';
    if ( empty( $endpoint ) ) {
        wp_send_json_error( 'Missing endpoint' );
    }

    $allowed_prefixes = array( 'player', 'tracks' );
    $first_segment    = explode( '/', ltrim( $endpoint, '/' ) )[0];
    if ( ! in_array( $first_segment, $allowed_prefixes, true ) ) {
        wp_send_json_error( 'Endpoint not allowed' );
    }

    $body = isset( $_POST['body'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['body'] ) ), true ) : array();
    if ( ! is_array( $body ) ) {
        $body = array();
    }

    $client   = new ELSFM_API_Client();
    $response = $client->post( $endpoint, $body );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    wp_send_json_success( $response );
}

/**
 * Initialize plugin.
 */
function elsfm_music_player() {
    return ELSFM_Music_Player::instance();
}

elsfm_music_player();
