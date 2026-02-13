<?php
/**
 * ELSFM Admin Settings
 *
 * Registers the plugin settings page under Settings > ELSFM Music.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ELSFM_Admin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_elsfm_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    public function add_menu() {
        add_options_page(
            __( 'ELSFM Music Player', 'elsfm-music-player' ),
            __( 'ELSFM Music', 'elsfm-music-player' ),
            'manage_options',
            'elsfm-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        // --- Connection Section ---
        add_settings_section(
            'elsfm_connection',
            __( 'API Connection', 'elsfm-music-player' ),
            function () {
                echo '<p>' . esc_html__( 'Configure the connection to your ELSFM music platform.', 'elsfm-music-player' ) . '</p>';
            },
            'elsfm-settings'
        );

        // API URL
        register_setting( 'elsfm_settings', 'elsfm_api_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://elsfm.com',
        ) );
        add_settings_field( 'elsfm_api_url', __( 'API Base URL', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_api_url', 'https://elsfm.com' );
            echo '<input type="url" name="elsfm_api_url" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="https://elsfm.com" />';
            echo '<p class="description">' . esc_html__( 'The base URL of your ELSFM installation (without /api/v1).', 'elsfm-music-player' ) . '</p>';
        }, 'elsfm-settings', 'elsfm_connection' );

        // API Token
        register_setting( 'elsfm_settings', 'elsfm_api_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field( 'elsfm_api_token', __( 'API Bearer Token', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_api_token', '' );
            echo '<input type="password" name="elsfm_api_token" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" />';
            echo '<p class="description">' . esc_html__( 'Your API token from ELSFM account settings. This token is kept server-side and never exposed to visitors.', 'elsfm-music-player' ) . '</p>';
        }, 'elsfm-settings', 'elsfm_connection' );

        // --- Display Section ---
        add_settings_section(
            'elsfm_display',
            __( 'Display Settings', 'elsfm-music-player' ),
            function () {
                echo '<p>' . esc_html__( 'Customize how music content appears on your site.', 'elsfm-music-player' ) . '</p>';
            },
            'elsfm-settings'
        );

        // Default items per page
        register_setting( 'elsfm_settings', 'elsfm_per_page', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 12,
        ) );
        add_settings_field( 'elsfm_per_page', __( 'Items Per Page', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_per_page', 12 );
            echo '<input type="number" name="elsfm_per_page" value="' . esc_attr( $value ) . '" min="1" max="100" class="small-text" />';
        }, 'elsfm-settings', 'elsfm_display' );

        // Accent color
        register_setting( 'elsfm_settings', 'elsfm_accent_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#1db954',
        ) );
        add_settings_field( 'elsfm_accent_color', __( 'Accent Color', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_accent_color', '#1db954' );
            echo '<input type="color" name="elsfm_accent_color" value="' . esc_attr( $value ) . '" />';
        }, 'elsfm-settings', 'elsfm_display' );

        // --- Performance Section ---
        add_settings_section(
            'elsfm_performance',
            __( 'Performance', 'elsfm-music-player' ),
            function () {
                echo '<p>' . esc_html__( 'API caching and timeout settings.', 'elsfm-music-player' ) . '</p>';
            },
            'elsfm-settings'
        );

        // Cache TTL
        register_setting( 'elsfm_settings', 'elsfm_cache_ttl', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ) );
        add_settings_field( 'elsfm_cache_ttl', __( 'Cache Duration (seconds)', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_cache_ttl', 300 );
            echo '<input type="number" name="elsfm_cache_ttl" value="' . esc_attr( $value ) . '" min="0" max="86400" class="small-text" />';
            echo '<p class="description">' . esc_html__( 'Set to 0 to disable caching. Default: 300 (5 minutes).', 'elsfm-music-player' ) . '</p>';
        }, 'elsfm-settings', 'elsfm_performance' );

        // API Timeout
        register_setting( 'elsfm_settings', 'elsfm_api_timeout', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 15,
        ) );
        add_settings_field( 'elsfm_api_timeout', __( 'API Timeout (seconds)', 'elsfm-music-player' ), function () {
            $value = get_option( 'elsfm_api_timeout', 15 );
            echo '<input type="number" name="elsfm_api_timeout" value="' . esc_attr( $value ) . '" min="5" max="60" class="small-text" />';
        }, 'elsfm-settings', 'elsfm_performance' );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_elsfm-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'elsfm-admin',
            ELSFM_PLUGIN_URL . 'assets/css/elsfm-admin.css',
            array(),
            ELSFM_VERSION
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once ELSFM_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * AJAX handler: test the API connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'elsfm_test_connection', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $client   = new ELSFM_API_Client();
        $response = $client->get( 'genres', array( 'perPage' => 1 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        wp_send_json_success( 'API responded successfully.' );
    }
}
