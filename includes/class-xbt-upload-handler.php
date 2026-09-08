<?php
/**
 * AJAX receipt upload handler.
 *
 * @package X_Bank_Transfer
 */

defined( 'ABSPATH' ) || exit;

/**
 * XBT_Upload_Handler - handles the checkout receipt upload via AJAX.
 */
class XBT_Upload_Handler {

	/**
	 * Handle the AJAX upload request.
	 */
	public static function handle_upload() {
		// Verify the nonce (CSRF protection).
		check_ajax_referer( 'xbt_upload_receipt', 'nonce' );

		// No file uploaded.
		if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'x-bank-transfer' ) ) );
		}

		$extension = strtolower( pathinfo( sanitize_text_field( $_FILES['file']['name'] ), PATHINFO_EXTENSION ) );

		// Supported file types.
		$valid_formats = apply_filters( 'xbt_allowed_receipt_types', array( 'jpg', 'jpeg', 'png', 'pdf' ) );

		if ( ! in_array( $extension, $valid_formats, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a JPG, PNG or PDF file.', 'x-bank-transfer' ) ) );
		}

		// Load WordPress upload helpers.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_overrides = array( 'test_form' => false );

		$movefile = wp_handle_upload( $_FILES['file'], $upload_overrides );

		if ( isset( $movefile['error'] ) ) {
			wp_send_json_error( array( 'message' => $movefile['error'] ) );
		}

		if ( empty( $movefile['file'] ) || empty( $movefile['url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'There was an error uploading the file.', 'x-bank-transfer' ) ) );
		}

		$filetype = wp_check_filetype( basename( $movefile['file'] ), null );

		$attachment = array(
			'guid'           => $movefile['url'],
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment and generate metadata.
		$attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			wp_send_json_error( array( 'message' => __( 'There was an error saving the receipt.', 'x-bank-transfer' ) ) );
		}

		// Tag the attachment so it can be cleaned up reliably on uninstall.
		update_post_meta( $attach_id, '_xbt_receipt', '1' );

		// Return the attachment ID as the success payload.
		wp_send_json_success( $attach_id );
	}
}
