<?php
/**
 * Plugin Name: WP Image Optimize
 * Description: Optimizes WordPress images locally — no API key or external service required.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WP Image Optimize
 * License: GPL-2.0-or-later
 * Text Domain: wp-image-optimize
 */

defined( 'ABSPATH' ) || exit;

final class WP_Image_Optimize {
	const OPTION = 'wpio_settings';
	const META   = '_wpio_optimized';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_new_upload' ), 20, 2 );
		add_filter( 'media_row_actions', array( $this, 'media_action' ), 10, 2 );
		add_action( 'admin_post_wpio_optimize', array( $this, 'handle_manual_optimization' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	public function admin_menu() {
		add_menu_page( 'WP Image Optimize', 'Image Optimize', 'manage_options', 'wp-image-optimize', array( $this, 'render_dashboard' ), 'dashicons-format-image', 80 );
		add_submenu_page( 'wp-image-optimize', 'Dashboard', 'Dashboard', 'manage_options', 'wp-image-optimize', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'wp-image-optimize', 'Settings', 'Settings', 'manage_options', 'wp-image-optimize-settings', array( $this, 'render_settings' ) );
	}

	public function admin_assets( $hook ) {
		if ( false === strpos( $hook, 'wp-image-optimize' ) ) {
			return;
		}
		wp_enqueue_style( 'wpio-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), '1.2.0' );
	}

	public function register_settings() {
		register_setting( 'wpio_settings', self::OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		return array(
			'quality'       => isset( $input['quality'] ) ? min( 95, max( 40, absint( $input['quality'] ) ) ) : 82,
			'auto'          => ! empty( $input['auto'] ) ? 1 : 0,
			'generate_webp' => ! empty( $input['generate_webp'] ) ? 1 : 0,
		);
	}

	public function render_dashboard() {
		$stats    = $this->stats();
		$settings = $this->settings();
		$recent   = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 5, 'meta_key' => self::META, 'orderby' => 'modified', 'order' => 'DESC' ) );
		?>
		<div class="wrap wpio-wrap">
			<section class="wpio-hero">
				<div><span class="wpio-kicker">LOCAL IMAGE OPTIMIZATION</span><h1>WP Image Optimize</h1><p>Your images stay on your server. No API key or third-party account needed.</p></div>
				<div class="wpio-hero-actions"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">Optimize an image <span class="dashicons dashicons-arrow-right-alt"></span></a><a class="wpio-text-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-image-optimize-settings' ) ); ?>">Open settings</a></div>
			</section>

			<section class="wpio-grid" aria-label="Optimization statistics">
				<article class="wpio-card wpio-card-featured"><span class="dashicons dashicons-chart-line"></span><p>Total space saved</p><strong><?php echo esc_html( size_format( $stats['saved'] ) ); ?></strong><small>Across optimized originals</small></article>
				<article class="wpio-card"><span class="dashicons dashicons-images-alt2"></span><p>Images optimized</p><strong><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?> images in Media Library</small></article>
				<article class="wpio-card"><span class="dashicons dashicons-performance"></span><p>Average reduction</p><strong><?php echo esc_html( $stats['percent'] ); ?>%</strong><small>Based on available size data</small></article>
				<article class="wpio-card"><span class="dashicons dashicons-format-image"></span><p>WebP copies</p><strong><?php echo esc_html( number_format_i18n( $stats['webp'] ) ); ?></strong><small><?php echo ! empty( $settings['generate_webp'] ) ? 'Generation is enabled' : 'Generation is turned off'; ?></small></article>
			</section>

			<div class="wpio-columns">
				<section class="wpio-panel wpio-activity"><div class="wpio-panel-heading"><div><span class="wpio-eyebrow">ACTIVITY</span><h2>Recently optimized</h2></div><a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">View Media Library</a></div>
				<?php if ( $recent ) : ?><ul class="wpio-recent-list"><?php foreach ( $recent as $attachment ) : $saved = absint( get_post_meta( $attachment->ID, '_wpio_saved_bytes', true ) ); ?><li><div class="wpio-thumb"><?php echo wp_kses_post( wp_get_attachment_image( $attachment->ID, 'thumbnail' ) ); ?></div><div><a href="<?php echo esc_url( get_edit_post_link( $attachment->ID ) ); ?>"><?php echo esc_html( get_the_title( $attachment ) ); ?></a><span><?php echo esc_html( human_time_diff( get_post_modified_time( 'U', true, $attachment ) ) . ' ago' ); ?><?php echo $saved ? ' · saved ' . esc_html( size_format( $saved ) ) : ''; ?></span></div><span class="wpio-status">Optimized</span></li><?php endforeach; ?></ul><?php else : ?><div class="wpio-empty"><span class="dashicons dashicons-format-image"></span><h3>Your dashboard is ready</h3><p>Upload a JPEG or PNG image, or optimize one from the Media Library, to see activity here.</p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">Open Media Library</a></div><?php endif; ?></section>

				<aside class="wpio-panel wpio-setup"><span class="wpio-eyebrow">QUICK STATUS</span><h2>Optimization setup</h2><div class="wpio-setting-row"><span>Automatic optimization</span><b class="<?php echo ! empty( $settings['auto'] ) ? 'is-on' : 'is-off'; ?>"><?php echo ! empty( $settings['auto'] ) ? 'On' : 'Off'; ?></b></div><div class="wpio-setting-row"><span>JPEG quality</span><b><?php echo esc_html( $settings['quality'] ); ?> / 100</b></div><div class="wpio-setting-row"><span>WebP copies</span><b class="<?php echo ! empty( $settings['generate_webp'] ) ? 'is-on' : 'is-off'; ?>"><?php echo ! empty( $settings['generate_webp'] ) ? 'On' : 'Off'; ?></b></div><a class="button button-secondary wpio-full-button" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-image-optimize-settings' ) ); ?>">Change settings</a><p class="wpio-note"><span class="dashicons dashicons-shield"></span> Images are processed locally on your server.</p></aside>
			</div>
		</div>
		<?php
	}

	public function render_settings() {
		$settings = $this->settings();
		?>
		<div class="wrap wpio-wrap"><section class="wpio-page-heading"><span class="wpio-kicker">CONFIGURATION</span><h1>Optimization settings</h1><p>Fine-tune how locally processed images are optimized.</p></section><section class="wpio-panel wpio-settings-panel"><form method="post" action="options.php"><?php settings_fields( 'wpio_settings' ); ?><div class="wpio-form-row"><div><label for="wpio-quality">JPEG quality</label><p>Lower values create smaller files. 82 is a balanced starting point.</p></div><input id="wpio-quality" name="<?php echo esc_attr( self::OPTION ); ?>[quality]" type="number" min="40" max="95" value="<?php echo esc_attr( $settings['quality'] ); ?>" /></div><div class="wpio-form-row"><div><label for="wpio-auto">Automatic optimization</label><p>Optimize new JPEG and PNG uploads automatically.</p></div><label class="wpio-switch"><input id="wpio-auto" name="<?php echo esc_attr( self::OPTION ); ?>[auto]" type="checkbox" value="1" <?php checked( $settings['auto'] ); ?> /><span></span></label></div><div class="wpio-form-row"><div><label for="wpio-webp">Generate WebP copies</label><p>Creates an additional WebP file when your server supports it. Your theme or server must serve it.</p></div><label class="wpio-switch"><input id="wpio-webp" name="<?php echo esc_attr( self::OPTION ); ?>[generate_webp]" type="checkbox" value="1" <?php checked( $settings['generate_webp'] ); ?> /><span></span></label></div><?php submit_button( 'Save settings', 'primary wpio-save-button' ); ?></form></section></div>
		<?php
	}

	public function optimize_new_upload( $metadata, $attachment_id ) {
		if ( ! empty( $this->settings()['auto'] ) && empty( get_post_meta( $attachment_id, self::META, true ) ) ) {
			$result = $this->optimize_attachment( $attachment_id );
			if ( is_wp_error( $result ) ) update_post_meta( $attachment_id, '_wpio_last_error', $result->get_error_message() );
		}
		return $metadata;
	}

	public function media_action( $actions, $post ) {
		if ( $this->is_supported_attachment( $post->ID ) && current_user_can( 'edit_post', $post->ID ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpio_optimize&attachment_id=' . $post->ID ), 'wpio_optimize_' . $post->ID );
			$actions['wpio_optimize'] = '<a href="' . esc_url( $url ) . '">Optimize locally</a>';
		}
		return $actions;
	}

	public function handle_manual_optimization() {
		$id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) wp_die( 'You are not allowed to optimize this image.' );
		check_admin_referer( 'wpio_optimize_' . $id );
		$result = $this->optimize_attachment( $id );
		wp_safe_redirect( add_query_arg( 'wpio_notice', is_wp_error( $result ) ? 'error' : 'success', admin_url( 'upload.php' ) ) );
		exit;
	}

	public function admin_notice() {
		if ( ! isset( $_GET['wpio_notice'] ) ) return;
		$success = 'success' === sanitize_key( $_GET['wpio_notice'] );
		echo '<div class="notice ' . esc_attr( $success ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $success ? 'Image optimized locally.' : 'Image could not be optimized. Check the attachment and server image-library support.' ) . '</p></div>';
	}

	private function optimize_attachment( $attachment_id ) {
		if ( ! $this->is_supported_attachment( $attachment_id ) ) return new WP_Error( 'wpio_unsupported', 'Only JPEG and PNG images can be optimized.' );
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) return new WP_Error( 'wpio_missing_file', 'The original image file could not be found.' );
		$before = filesize( $file );
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) return $editor;
		$settings = $this->settings();
		$editor->set_quality( $settings['quality'] );
		$saved = $editor->save( $file, get_post_mime_type( $attachment_id ) );
		if ( is_wp_error( $saved ) ) return $saved;
		$after = filesize( $file );
		$webp_file = '';
		if ( ! empty( $settings['generate_webp'] ) && $editor->supports_mime_type( 'image/webp' ) ) {
			$webp_file = preg_replace( '/\.[^.]+$/', '.webp', $file );
			$webp = $editor->save( $webp_file, 'image/webp' );
			if ( is_wp_error( $webp ) ) $webp_file = '';
		}
		$saved_bytes = max( 0, $before - $after );
		update_post_meta( $attachment_id, self::META, current_time( 'mysql' ) );
		update_post_meta( $attachment_id, '_wpio_original_size', $before );
		update_post_meta( $attachment_id, '_wpio_optimized_size', $after );
		update_post_meta( $attachment_id, '_wpio_saved_bytes', $saved_bytes );
		update_post_meta( $attachment_id, '_wpio_webp', $webp_file );
		delete_post_meta( $attachment_id, '_wpio_last_error' );
		return true;
	}

	private function stats() {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META ) );
		$saved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wpio_saved_bytes' ) );
		$original = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wpio_original_size' ) );
		$webp = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''", '_wpio_webp' ) );
		$total = (int) wp_count_attachments( 'image' );
		return array( 'count' => $count, 'saved' => $saved, 'percent' => $original ? round( $saved / $original * 100, 1 ) : 0, 'webp' => $webp, 'total' => $total );
	}

	private function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), array( 'quality' => 82, 'auto' => 1, 'generate_webp' => 1 ) );
	}

	private function is_supported_attachment( $attachment_id ) {
		return in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png' ), true );
	}
}

new WP_Image_Optimize();
