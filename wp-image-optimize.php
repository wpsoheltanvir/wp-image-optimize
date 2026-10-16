<?php
/**
 * Plugin Name: WP Image Optimize
 * Description: Optimizes WordPress media images with your Imagify API key.
 * Version: 1.0.0
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
	const API    = 'https://app.imagify.io/api/upload/';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_new_upload' ), 20, 2 );
		add_filter( 'media_row_actions', array( $this, 'media_action' ), 10, 2 );
		add_action( 'admin_post_wpio_optimize', array( $this, 'handle_manual_optimization' ) );
	}

	public function settings_page() {
		add_options_page( 'WP Image Optimize', 'WP Image Optimize', 'manage_options', 'wp-image-optimize', array( $this, 'render_settings' ) );
	}

	public function register_settings() {
		register_setting( 'wpio_settings', self::OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		return array(
			'api_key' => isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '',
			'level'   => isset( $input['level'] ) && in_array( $input['level'], array( 'normal', 'aggressive', 'ultra' ), true ) ? $input['level'] : 'aggressive',
			'auto'    => ! empty( $input['auto'] ) ? 1 : 0,
		);
	}

	public function render_settings() {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), array( 'api_key' => '', 'level' => 'aggressive', 'auto' => 1 ) );
		?>
		<div class="wrap"><h1>WP Image Optimize</h1>
		<p>Connect your Imagify account, then optimize new uploads automatically or use the Media Library action for an existing image.</p>
		<form method="post" action="options.php"><?php settings_fields( 'wpio_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="wpio-api-key">Imagify API key</label></th><td><input id="wpio-api-key" name="<?php echo esc_attr( self::OPTION ); ?>[api_key]" type="password" class="regular-text" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" /><p class="description">Stored in your WordPress database. Find it in your Imagify account’s API &amp; Integration area.</p></td></tr>
			<tr><th scope="row">Compression level</th><td><select name="<?php echo esc_attr( self::OPTION ); ?>[level]"><?php foreach ( array( 'normal' => 'Normal (lossless)', 'aggressive' => 'Aggressive', 'ultra' => 'Ultra' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['level'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th scope="row">Automatic optimization</th><td><label><input name="<?php echo esc_attr( self::OPTION ); ?>[auto]" type="checkbox" value="1" <?php checked( $settings['auto'] ); ?> /> Optimize supported images when uploaded</label></td></tr>
		</table><?php submit_button(); ?></form></div>
		<?php
	}

	public function optimize_new_upload( $metadata, $attachment_id ) {
		$settings = $this->settings();
		if ( empty( $settings['auto'] ) || ! empty( get_post_meta( $attachment_id, self::META, true ) ) ) {
			return $metadata;
		}
		$result = $this->optimize_attachment( $attachment_id );
		if ( is_wp_error( $result ) ) {
			update_post_meta( $attachment_id, '_wpio_last_error', $result->get_error_message() );
		}
		return $metadata;
	}

	public function media_action( $actions, $post ) {
		if ( $this->is_supported_attachment( $post->ID ) && current_user_can( 'upload_files' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpio_optimize&attachment_id=' . $post->ID ), 'wpio_optimize_' . $post->ID );
			$actions['wpio_optimize'] = '<a href="' . esc_url( $url ) . '">Optimize with Imagify</a>';
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

	private function optimize_attachment( $attachment_id ) {
		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return new WP_Error( 'wpio_unsupported', 'Only JPEG, PNG, and GIF images can be optimized.' );
		}
		$settings = $this->settings();
		if ( empty( $settings['api_key'] ) ) {
			return new WP_Error( 'wpio_missing_key', 'Add an Imagify API key in Settings > WP Image Optimize.' );
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'wpio_missing_file', 'The original image file could not be found.' );
		}
		if ( ! function_exists( 'curl_file_create' ) ) {
			return new WP_Error( 'wpio_curl_missing', 'PHP cURL support is required to upload images to Imagify.' );
		}
		$payload = array(
			'image' => curl_file_create( $file, get_post_mime_type( $attachment_id ), basename( $file ) ),
			'data'  => wp_json_encode( array( $settings['level'] => true ) ),
		);
		$response = wp_remote_post( self::API, array(
			'timeout' => 60,
			'headers' => array( 'Authorization' => 'token ' . $settings['api_key'] ),
			'body'    => $payload,
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) || empty( $data['image'] ) ) {
			return new WP_Error( 'wpio_api_error', ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : 'Imagify did not return an optimized image.' );
		}
		$download = wp_safe_remote_get( esc_url_raw( $data['image'] ), array( 'timeout' => 60 ) );
		if ( is_wp_error( $download ) || 200 !== (int) wp_remote_retrieve_response_code( $download ) ) {
			return new WP_Error( 'wpio_download_error', 'The optimized image could not be downloaded from Imagify.' );
		}
		$written = file_put_contents( $file, wp_remote_retrieve_body( $download ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			return new WP_Error( 'wpio_write_error', 'WordPress could not replace the local image.' );
		}
		update_post_meta( $attachment_id, self::META, array( 'at' => current_time( 'mysql' ), 'original_size' => isset( $data['original_size'] ) ? absint( $data['original_size'] ) : 0, 'new_size' => isset( $data['new_size'] ) ? absint( $data['new_size'] ) : 0 ) );
		delete_post_meta( $attachment_id, '_wpio_last_error' );
		return true;
	}

	private function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), array( 'api_key' => '', 'level' => 'aggressive', 'auto' => 1 ) );
	}

	private function is_supported_attachment( $attachment_id ) {
		return in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png', 'image/gif' ), true );
	}
}

new WP_Image_Optimize();
