<?php
/**
 * Admin settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap elsfm-admin-wrap">
    <h1><?php esc_html_e( 'ELSFM Music Player Settings', 'elsfm-music-player' ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'elsfm_settings' );
        do_settings_sections( 'elsfm-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <div class="elsfm-admin-section">
        <h2><?php esc_html_e( 'Connection Test', 'elsfm-music-player' ); ?></h2>
        <p><?php esc_html_e( 'Verify your API connection is working.', 'elsfm-music-player' ); ?></p>
        <button type="button" id="elsfm-test-connection" class="button button-secondary">
            <?php esc_html_e( 'Test Connection', 'elsfm-music-player' ); ?>
        </button>
        <span id="elsfm-test-result"></span>
    </div>

    <hr />

    <div class="elsfm-admin-section">
        <h2><?php esc_html_e( 'Available Shortcodes', 'elsfm-music-player' ); ?></h2>
        <table class="widefat elsfm-shortcode-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Shortcode', 'elsfm-music-player' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'elsfm-music-player' ); ?></th>
                    <th><?php esc_html_e( 'Parameters', 'elsfm-music-player' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[elsfm_track id="123"]</code></td>
                    <td><?php esc_html_e( 'Display a single track with play button', 'elsfm-music-player' ); ?></td>
                    <td><code>id</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>)</td>
                </tr>
                <tr>
                    <td><code>[elsfm_album id="123"]</code></td>
                    <td><?php esc_html_e( 'Display an album with its tracklist', 'elsfm-music-player' ); ?></td>
                    <td><code>id</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>)</td>
                </tr>
                <tr>
                    <td><code>[elsfm_artist id="123"]</code></td>
                    <td><?php esc_html_e( 'Display an artist profile with top tracks and albums', 'elsfm-music-player' ); ?></td>
                    <td><code>id</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>)</td>
                </tr>
                <tr>
                    <td><code>[elsfm_playlist id="123"]</code></td>
                    <td><?php esc_html_e( 'Display a playlist with its tracks', 'elsfm-music-player' ); ?></td>
                    <td><code>id</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>)</td>
                </tr>
                <tr>
                    <td><code>[elsfm_tracks]</code></td>
                    <td><?php esc_html_e( 'Display a list or grid of tracks', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>limit</code> (<?php esc_html_e( 'default: 12', 'elsfm-music-player' ); ?>),
                        <code>orderBy</code> (created_at, plays, name),
                        <code>orderDir</code> (asc, desc),
                        <code>layout</code> (list, grid)
                    </td>
                </tr>
                <tr>
                    <td><code>[elsfm_albums]</code></td>
                    <td><?php esc_html_e( 'Display a grid of albums', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>limit</code>,
                        <code>orderBy</code>,
                        <code>orderDir</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[elsfm_artists]</code></td>
                    <td><?php esc_html_e( 'Display a grid of artists', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>limit</code>,
                        <code>orderBy</code>,
                        <code>orderDir</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[elsfm_search]</code></td>
                    <td><?php esc_html_e( 'Display a search form, or static results with a query', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>query</code>,
                        <code>types</code> (track,album,artist,playlist),
                        <code>limit</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[elsfm_genre name="rock"]</code></td>
                    <td><?php esc_html_e( 'Display tracks tagged with a genre/tag', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>name</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>),
                        <code>limit</code>
                    </td>
                </tr>
                <tr>
                    <td><code>[elsfm_radio type="artist" id="1"]</code></td>
                    <td><?php esc_html_e( 'Display radio/recommendations based on a seed', 'elsfm-music-player' ); ?></td>
                    <td>
                        <code>type</code> (artist, track, genre),
                        <code>id</code> (<?php esc_html_e( 'required', 'elsfm-music-player' ); ?>)
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('elsfm-test-connection').addEventListener('click', function() {
    var btn = this;
    var result = document.getElementById('elsfm-test-result');
    btn.disabled = true;
    result.textContent = '<?php echo esc_js( __( 'Testing...', 'elsfm-music-player' ) ); ?>';
    result.className = '';

    fetch(ajaxurl + '?action=elsfm_test_connection&nonce=<?php echo esc_js( wp_create_nonce( 'elsfm_test_connection' ) ); ?>')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            if (data.success) {
                result.textContent = '<?php echo esc_js( __( 'Connected successfully!', 'elsfm-music-player' ) ); ?>';
                result.className = 'elsfm-success';
            } else {
                result.textContent = '<?php echo esc_js( __( 'Connection failed: ', 'elsfm-music-player' ) ); ?>' + (data.data || 'Unknown error');
                result.className = 'elsfm-error-text';
            }
        })
        .catch(function() {
            btn.disabled = false;
            result.textContent = '<?php echo esc_js( __( 'Request failed.', 'elsfm-music-player' ) ); ?>';
            result.className = 'elsfm-error-text';
        });
});
</script>
