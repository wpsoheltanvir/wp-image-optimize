<?php
/**
 * Plugin Name: WP Image Optimize
 * Description: Optimizes WordPress images locally — no API key or external service required.
 * Version: 1.1.0
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
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_new_upload' ), 20, 2 );
		add_filter( 'media_row_actions', array( $this, 'media_action' ), 10, 2 );
		add_action( 'admin_post_wpio_optimize', array( $this, 'handle_manual_optimization' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	public function settings_page() {
		add_options_page( 'WP Image Optimize', 'WP Image Optimize', 'manage_options', 'wp-image-optimize', array( $this, 'render_settings' ) );
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

	public function render_settings() {
		$settings = $this->settings();
		?>
		<div class="wrap"><h1>WP Image Optimize</h1>
		<p>Optimize images using your WordPress server. No API key, subscription, or third-party upload is required.</p>
		<form method="post" action="options.php"><?php settings_fields( 'wpio_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="wpio-quality">JPEG quality</label></th><td><input id="wpio-quality" name="<?php echo esc_attr( self::OPTION ); ?>[quality]" type="number" min="40" max="95" value="<?php echo esc_attr( $settings['quality'] ); ?>" /><p class="description">Lower values produce smaller files. 82 is a balanced default.</p></td></tr>
			<tr><th scope="row">Automatic optimization</th><td><label><input name="<?php echo esc_attr( self::OPTION ); ?>[auto]" type="checkbox" value="1" <?php checked( $settings['auto'] ); ?> /> Optimize supported images when uploaded</label></td></tr>
			<tr><th scope="row">Generate WebP copies</th><td><label><input name="<?php echo esc_attr( self::OPTION ); ?>[generate_webp]" type="checkbox" value="1" <?php checked( $settings['generate_webp'] ); ?> /> Create a WebP copy when the server image library supports it</label><p class="description">Your theme or server must be configured to serve the generated WebP files.</p></td></tr>
		</table><?php submit_button(); ?></form></div>
		<?php
	}

	public function optimize_new_upload( $metadata, $attachment_id ) {
		$settings = $this->settings();
		if ( ! empty( $settings['auto'] ) && empty( get_post_meta( $attachment_id, self::META, true ) ) ) {
			$result = $this->optimize_attachment( $attachment_id );
			if ( is_wp_error( $result ) ) {
				update_post_meta( $attachment_id, '_wpio_last_error', $result->get_error_message() );
			}
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
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'You are not allowed to optimize this image.' );
		}
		check_admin_referer( 'wpio_optimize_' . $id );
		$result = $this->optimize_attachment( $id );
		$redirect = add_query_arg( 'wpio_notice', is_wp_error( $result ) ? 'error' : 'success', admin_url( 'upload.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public function admin_notice() {
		if ( ! isset( $_GET['wpio_notice'] ) ) {
			return;
		}
		$class = 'success' === sanitize_key( $_GET['wpio_notice'] ) ? 'notice-success' : 'notice-error';
		$message = 'success' === sanitize_key( $_GET['wpio_notice'] ) ? 'Image optimized locally.' : 'Image could not be optimized. Check the attachment and server image-library support.';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function optimize_attachment( $attachment_id ) {
		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return new WP_Error( 'wpio_unsupported', 'Only JPEG and PNG images can be optimized.' );
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'wpio_missing_file', 'The original image file could not be found.' );
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$settings = $this->settings();
		$editor->set_quality( $settings['quality'] );
		$saved = $editor->save( $file, get_post_mime_type( $attachment_id ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$webp_file = '';
		if ( ! empty( $settings['generate_webp'] ) && $editor->supports_mime_type( 'image/webp' ) ) {
			$webp_file = preg_replace( '/\.[^.]+$/', '.webp', $file );
			$webp = $editor->save( $webp_file, 'image/webp' );
			if ( is_wp_error( $webp ) ) {
				$webp_file = '';
			}
		}
		update_post_meta( $attachment_id, self::META, array( 'at' => current_time( 'mysql' ), 'quality' => $settings['quality'], 'webp' => $webp_file ) );
		delete_post_meta( $attachment_id, '_wpio_last_error' );
		return true;
	}

	private function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), array( 'quality' => 82, 'auto' => 1, 'generate_webp' => 1 ) );
	}

	private function is_supported_attachment( $attachment_id ) {
		return in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png' ), true );
	}
}

new WP_Image_Optimize();
