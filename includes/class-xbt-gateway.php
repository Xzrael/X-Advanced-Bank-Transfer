<?php
/**
 * X Bank Transfer WooCommerce payment gateway.
 *
 * @package X_Bank_Transfer
 */

defined( 'ABSPATH' ) || exit;

/**
 * XBT_Gateway - WooCommerce payment gateway for bank transfers with receipt upload.
 */
class XBT_Gateway extends WC_Payment_Gateway {

	/**
	 * Account details (bank accounts) saved by the admin.
	 *
	 * @var array
	 */
	public $account_details;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'xbt_gateway';
		$this->icon               = apply_filters( 'xbt_gateway_icon', '' );
		$this->has_fields         = true;
		$this->method_title       = __( 'X Bank Transfer', 'x-bank-transfer' );
		$this->method_description = __( 'Accept direct bank transfers. Customers upload their payment receipt during checkout so you can verify and download it from the order.', 'x-bank-transfer' );

		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		$this->account_details = $this->get_option( 'account_details', array() );

		// Save gateway options.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialize gateway settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = apply_filters(
			'xbt_gateway_form_fields',
			array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'x-bank-transfer' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable X Bank Transfer', 'x-bank-transfer' ),
					'default' => 'yes',
				),
				'title' => array(
					'title'       => __( 'Title', 'x-bank-transfer' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'x-bank-transfer' ),
					'default'     => __( 'X Bank Transfer', 'x-bank-transfer' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'x-bank-transfer' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description shown on checkout.', 'x-bank-transfer' ),
					'default'     => __( 'Make your payment directly into our bank account using the details below, then upload your bank payment receipt. Kindly use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'x-bank-transfer' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'x-bank-transfer' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions shown on the order received (thank you) page and in order emails.', 'x-bank-transfer' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'account_details' => array(
					'title' => __( 'Bank account details', 'x-bank-transfer' ),
					'type'  => 'xbt_account_details',
				),
				'styling' => array(
					'title'             => __( 'Checkout styling', 'x-bank-transfer' ),
					'type'              => 'xbt_styling',
					'description'       => __( 'Customize the colors of the bank details and receipt upload box shown at checkout. Colors are applied as CSS variables and always fall back to the brand colors if anything is invalid.', 'x-bank-transfer' ),
					'sanitize_callback' => array( $this, 'validate_xbt_styling_field' ),
				),
			)
		);
	}

	/**
	 * Custom field type: render the bank account details table.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_xbt_account_details_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);
		$data      = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ); ?></label>
			</th>
			<td class="forminp" id="xbt_bank_accounts">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php esc_html_e( 'Account name', 'x-bank-transfer' ); ?></th>
								<th><?php esc_html_e( 'Account number', 'x-bank-transfer' ); ?></th>
								<th><?php esc_html_e( 'Bank name', 'x-bank-transfer' ); ?></th>
								<th><?php esc_html_e( 'IFSC / Sort code', 'x-bank-transfer' ); ?></th>
								<th><?php esc_html_e( 'IBAN', 'x-bank-transfer' ); ?></th>
								<th><?php esc_html_e( 'BIC / Swift', 'x-bank-transfer' ); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$accounts = $this->get_option( 'account_details', array() );
							$i        = -1;

							if ( ! empty( $accounts ) && is_array( $accounts ) ) {
								foreach ( $accounts as $account ) {
									$i++;
									?>
									<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['account_name'] ) ? $account['account_name'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][account_name]" /></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['account_number'] ) ? $account['account_number'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][account_number]" /></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['bank_name'] ) ? $account['bank_name'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][bank_name]" /></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['ifsc'] ) ? $account['ifsc'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][ifsc]" /></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['iban'] ) ? $account['iban'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][iban]" /></td>
										<td><input type="text" value="<?php echo esc_attr( isset( $account['bic'] ) ? $account['bic'] : '' ); ?>" name="<?php echo esc_attr( $field_key ); ?>[<?php echo esc_attr( $i ); ?>][bic]" /></td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'x-bank-transfer' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected', 'x-bank-transfer' ); ?></a></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<script type="text/javascript">
					jQuery( function() {
						jQuery( '#xbt_bank_accounts' ).on( 'click', 'a.add', function() {
							var size = jQuery( '#xbt_bank_accounts tbody.account, #xbt_bank_accounts tbody .account' ).length;
							jQuery(
								'<tr class="account">' +
								'<td class="sort"></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][account_name]" /></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][account_number]" /></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][bank_name]" /></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][ifsc]" /></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][iban]" /></td>' +
								'<td><input type="text" name="<?php echo esc_js( $field_key ); ?>[' + size + '][bic]" /></td>' +
								'</tr>'
							).appendTo( '#xbt_bank_accounts table tbody' );
							return false;
						} );
					} );
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate / sanitize the account details custom field.
	 *
	 * Called by WooCommerce's settings API because the field type is
	 * `xbt_account_details`. WooCommerce does not pass posted values into
	 * validators for custom composite fields, so the array is read directly
	 * from $_POST using the field key.
	 *
	 * @param mixed $key_or_value Field key (WC version dependent).
	 * @param mixed $value        Unused (WC version dependent).
	 * @return array
	 */
	public function validate_xbt_account_details_field( $key_or_value, $value = false ) {
		// WooCommerce's settings API does not inject posted values for custom
		// composite fields, so we read them straight from $_POST.
		$field_key = $this->get_field_key( 'account_details' );
		$posted    = isset( $_POST[ $field_key ] ) && is_array( $_POST[ $field_key ] )
			? wp_unslash( $_POST[ $field_key ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		$clean = array();

		foreach ( $posted as $account ) {
			if ( ! is_array( $account ) ) {
				continue;
			}
			$clean[] = array(
				'account_name'   => isset( $account['account_name'] ) ? sanitize_text_field( $account['account_name'] ) : '',
				'account_number' => isset( $account['account_number'] ) ? sanitize_text_field( $account['account_number'] ) : '',
				'bank_name'      => isset( $account['bank_name'] ) ? sanitize_text_field( $account['bank_name'] ) : '',
				'ifsc'           => isset( $account['ifsc'] ) ? sanitize_text_field( $account['ifsc'] ) : '',
				'iban'           => isset( $account['iban'] ) ? sanitize_text_field( $account['iban'] ) : '',
				'bic'            => isset( $account['bic'] ) ? sanitize_text_field( $account['bic'] ) : '',
			);
		}

		return $clean;
	}

	/**
	 * Render the checkout styling editor (colors + source + live preview).
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_xbt_styling_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'       => '',
			'description' => '',
		);
		$data      = wp_parse_args( $data, $defaults );

		$current = $this->get_option(
			'styling',
			array(
				'source'  => 'manual',
				'primary' => '#9804CC',
				'accent'  => '#FFBE56',
			)
		);

		$source  = ( isset( $current['source'] ) && 'theme' === $current['source'] ) ? 'theme' : 'manual';
		$primary = XBT_Plugin::sanitize_hex_color( isset( $current['primary'] ) ? $current['primary'] : '' );
		$accent  = XBT_Plugin::sanitize_hex_color( isset( $current['accent'] ) ? $current['accent'] : '' );

		if ( ! $primary ) {
			$primary = '#9804CC';
		}
		if ( ! $accent ) {
			$accent = '#FFBE56';
		}

		ob_start();
		?>
		<tr valign="top" class="xbt-styling-row">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ); ?></label>
				<?php if ( $data['description'] ) : ?>
					<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $data['description'] ); ?>"></span>
				<?php endif; ?>
			</th>
			<td class="forminp">
				<div class="xbt-styling-editor" style="max-width:760px;">
					<p class="xbt-styling-source">
						<label for="<?php echo esc_attr( $field_key . '_source' ); ?>" style="display:inline-block;min-width:130px;font-weight:600;">
							<?php esc_html_e( 'Color source', 'x-bank-transfer' ); ?>
						</label>
						<select name="<?php echo esc_attr( $field_key ); ?>[source]" id="<?php echo esc_attr( $field_key . '_source' ); ?>" class="xbt-source-select">
							<option value="manual" <?php selected( $source, 'manual' ); ?>><?php esc_html_e( 'Manual (use the colors below)', 'x-bank-transfer' ); ?></option>
							<option value="theme" <?php selected( $source, 'theme' ); ?>><?php esc_html_e( 'Automatic (use my theme colors)', 'x-bank-transfer' ); ?></option>
						</select>
					</p>

					<p class="xbt-styling-colors xbt-source-manual">
						<label for="<?php echo esc_attr( $field_key . '_primary' ); ?>" style="display:inline-block;min-width:130px;font-weight:600;">
							<?php esc_html_e( 'Primary color', 'x-bank-transfer' ); ?>
						</label>
						<input type="color" value="<?php echo esc_attr( $primary ); ?>" data-xbt-color="primary" />
						<input type="text" name="<?php echo esc_attr( $field_key ); ?>[primary]" id="<?php echo esc_attr( $field_key . '_primary' ); ?>" class="xbt-color-text" data-xbt-color="primary" value="<?php echo esc_attr( $primary ); ?>" size="10" />
					</p>

					<p class="xbt-styling-colors xbt-source-manual">
						<label for="<?php echo esc_attr( $field_key . '_accent' ); ?>" style="display:inline-block;min-width:130px;font-weight:600;">
							<?php esc_html_e( 'Accent color', 'x-bank-transfer' ); ?>
						</label>
						<input type="color" value="<?php echo esc_attr( $accent ); ?>" data-xbt-color="accent" />
						<input type="text" name="<?php echo esc_attr( $field_key ); ?>[accent]" id="<?php echo esc_attr( $field_key . '_accent' ); ?>" class="xbt-color-text" data-xbt-color="accent" value="<?php echo esc_attr( $accent ); ?>" size="10" />
					</p>

					<div class="xbt-styling-preview" data-primary="<?php echo esc_attr( $primary ); ?>" data-accent="<?php echo esc_attr( $accent ); ?>">
						<h4 style="margin:0 0 8px;color:#666;"><?php esc_html_e( 'Live preview', 'x-bank-transfer' ); ?></h4>
						<div class="xbt-preview-box">
							<div class="xbt-preview-upload">
								<label><?php esc_html_e( 'Upload the bank payment receipt', 'x-bank-transfer' ); ?></label>
								<span class="xbt-preview-file"><?php esc_html_e( 'Select receipt file…', 'x-bank-transfer' ); ?></span>
							</div>
						</div>
					</div>

					<p class="description" style="margin-top:10px;">
						<?php esc_html_e( 'In "Automatic" mode the plugin tries to read your theme/WordPress palette colors. If none are found it falls back to the manual colors below.', 'x-bank-transfer' ); ?>
					</p>
				</div>

				<script type="text/javascript">
					jQuery( function( $ ) {
						var $row    = $( '.xbt-styling-editor' );
						var $source = $row.find( '.xbt-source-select' );
						var $box    = $row.find( '.xbt-styling-preview' );

						function hexToRgb( hex ) {
							hex = ( hex || '' ).replace( '#', '' );
							if ( hex.length === 3 ) { hex = hex.split( '' ).map( function( c ) { return c + c; } ).join( '' ); }
							var n = parseInt( hex, 16 );
							if ( isNaN( n ) || hex.length !== 6 ) { return null; }
							return { r: ( n >> 16 ) & 255, g: ( n >> 8 ) & 255, b: n & 255 };
						}

						function toRgba( hex, a ) {
							var c = hexToRgb( hex );
							return c ? 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + a + ')' : '';
						}

						function toDarker( hex, factor ) {
							var c = hexToRgb( hex );
							if ( ! c ) { return hex; }
							factor = factor || 0.12;
							var r = Math.max( 0, Math.round( c.r * ( 1 - factor ) ) );
							var g = Math.max( 0, Math.round( c.g * ( 1 - factor ) ) );
							var b = Math.max( 0, Math.round( c.b * ( 1 - factor ) ) );
							function h( n ) { n = n.toString( 16 ); return n.length === 1 ? '0' + n : n; }
							return '#' + h( r ) + h( g ) + h( b );
						}

						function applyPreview() {
							var el     = $box[0];
							var primary = $box.data( 'primary' ) || '#9804CC';
							var accent  = $box.data( 'accent' ) || '#FFBE56';
							if ( ! el ) { return; }
							var s = el.style;
							s.setProperty( '--xbt-primary', primary );
							s.setProperty( '--xbt-accent', accent );
							s.setProperty( '--xbt-primary-soft', toRgba( primary, 0.06 ) );
							s.setProperty( '--xbt-accent-soft', toRgba( accent, 0.10 ) );
							s.setProperty( '--xbt-primary-border', toRgba( primary, 0.15 ) );
							s.setProperty( '--xbt-primary-dashed', toRgba( primary, 0.35 ) );
							s.setProperty( '--xbt-primary-darker', toDarker( primary, 0.12 ) );
						}

						$row.on( 'input change', 'input[data-xbt-color]', function() {
							var which = $( this ).attr( 'data-xbt-color' );
							var $color = $row.find( 'input[data-xbt-color="' + which + '"]' );
							var val    = $( this ).val();

							if ( ! /^#[0-9a-fA-F]{3,6}$/.test( val ) ) {
								return;
							}

							$color.val( val );
							$box.data( which, val );
							applyPreview();
						} );

						$source.on( 'change', function() {
							$row.find( '.xbt-styling-colors' ).toggle( $( this ).val() === 'manual' );
						} ).trigger( 'change' );

						applyPreview();
					} );
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate / sanitize the checkout styling field.
	 *
	 * Reads the submitted array straight from $_POST because the WooCommerce
	 * settings API does not pre-process composite custom fields.
	 *
	 * @param mixed $key_or_value Field key or submitted value (WC version dependent).
	 * @param mixed $value        Submitted value (or false when value passed as first arg).
	 * @return array
	 */
	public function validate_xbt_styling_field( $key_or_value, $value = false ) {
		$field_key = $this->get_field_key( 'styling' );
		$posted    = isset( $_POST[ $field_key ] ) && is_array( $_POST[ $field_key ] )
			? wp_unslash( $_POST[ $field_key ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$clean = array(
			'source'  => 'manual',
			'primary' => '#9804CC',
			'accent'  => '#FFBE56',
		);

		if ( isset( $posted['source'] ) && 'theme' === $posted['source'] ) {
			$clean['source'] = 'theme';
		}

		if ( isset( $posted['primary'] ) ) {
			$primary = XBT_Plugin::sanitize_hex_color( $posted['primary'] );
			if ( $primary ) {
				$clean['primary'] = $primary;
			}
		}

		if ( isset( $posted['accent'] ) ) {
			$accent = XBT_Plugin::sanitize_hex_color( $posted['accent'] );
			if ( $accent ) {
				$clean['accent'] = $accent;
			}
		}

		return $clean;
	}

	/**
	 * Validate submitted payment fields on checkout.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce during checkout.
		$attach_id = isset( $_POST['xbt_attach_id'] ) ? absint( $_POST['xbt_attach_id'] ) : 0;

		if ( empty( $attach_id ) || 'attachment' !== get_post_type( $attach_id ) ) {
			// Friendly, human message.
			wc_add_notice( __( 'Kindly upload your bank payment receipt before placing your order.', 'x-bank-transfer' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Output the payment fields on the checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		$this->bank_details();
		?>
		<div id="custom_input" class="form-row form-row-wide">
			<label for="bank_payment_receipt">
				<?php esc_html_e( 'Upload the bank payment receipt', 'x-bank-transfer' ); ?>
			</label>
			<input type="file" name="xbt_bank_receipt" id="bank_payment_receipt" class="bank_payment_receipt" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
			<input type="hidden" name="xbt_attach_id" id="xbt_attach_id" class="xbt_attach_id" value="">
			<p class="xbt-upload-status" id="xbt_upload_status"></p>
		</div>
		<?php
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Save the uploaded receipt attachment ID as order meta.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		if ( isset( $_POST['xbt_attach_id'] ) ) {
			$order->update_meta_data( '_xbt_receipt_attachment_id', absint( $_POST['xbt_attach_id'] ) );
			$order->save();
		}

		// Mark as on-hold (we are awaiting the payment / funds to clear).
		$order->update_status( 'on-hold', __( 'Awaiting X Bank Transfer payment', 'x-bank-transfer' ) );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Clear the cart.
		WC()->cart->empty_cart();

		// Return the thank-you redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Output bank details as a list on the checkout page.
	 */
	private function bank_details() {
		$accounts = $this->account_details;

		if ( empty( $accounts ) || ! is_array( $accounts ) ) {
			return;
		}

		$has_details = false;
		$html        = '';

		foreach ( $accounts as $account ) {
			$account = wp_parse_args(
				(array) $account,
				array(
					'account_name'   => '',
					'account_number' => '',
					'bank_name'      => '',
					'ifsc'           => '',
					'iban'           => '',
					'bic'            => '',
				)
			);

			if ( ! empty( $account['account_name'] ) ) {
				$html .= '<p class="wc-bacs-bank-details-account-name"><u>' . esc_html( $account['account_name'] ) . '</u>:</p>';
			}

			$html .= '<ul class="wc-bacs-bank-details order_details bacs_details">';

			$fields = array(
				'bank_name'      => array(
					'label' => __( 'Bank', 'x-bank-transfer' ),
					'value' => $account['bank_name'],
				),
				'account_number' => array(
					'label' => __( 'Account number', 'x-bank-transfer' ),
					'value' => $account['account_number'],
				),
				'ifsc'           => array(
					'label' => __( 'IFSC / Sort code', 'x-bank-transfer' ),
					'value' => $account['ifsc'],
				),
				'iban'           => array(
					'label' => __( 'IBAN', 'x-bank-transfer' ),
					'value' => $account['iban'],
				),
				'bic'            => array(
					'label' => __( 'BIC / Swift', 'x-bank-transfer' ),
					'value' => $account['bic'],
				),
			);

			foreach ( $fields as $key => $field ) {
				if ( ! empty( $field['value'] ) ) {
					$has_details = true;
					// Bank name and account number get their uppercase labels
					// (BANK / ACCOUNT) from the CSS ::before rule, so only the
					// value is printed. Other optional fields keep an inline label.
					if ( 'bank_name' === $key || 'account_number' === $key ) {
						$html .= '<li class="' . esc_attr( $key ) . '"><strong>' . esc_html( $field['value'] ) . '</strong></li>';
					} else {
						$html .= '<li class="' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . ': <strong>' . esc_html( $field['value'] ) . '</strong></li>';
					}
				}
			}

			$html .= '</ul>';
		}

		if ( $has_details ) {
			echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__( 'Our bank details', 'x-bank-transfer' ) . '</h2>' . wp_kses_post( $html ) . '</section>';
		}
	}

	/**
	 * Output content on the order received (thank you) page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}

	/**
	 * Add content to WooCommerce order emails (customer + admin).
	 * This ensures the uploaded receipt is visible in the order emails.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether email is sent to admin.
	 * @param bool     $plain_text    Whether plain-text email.
	 * @param string   $email         Email ID.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false, $email = '' ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( $this->instructions ) {
			if ( $plain_text ) {
				echo wptexturize( $this->instructions ) . PHP_EOL . PHP_EOL;
			} else {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}

		// Show a link to the uploaded receipt.
		$attach_id = $order->get_meta( '_xbt_receipt_attachment_id' );
		if ( ! empty( $attach_id ) ) {
			$src = wp_get_attachment_url( $attach_id );
			if ( $src ) {
				if ( $plain_text ) {
					echo esc_html__( 'Bank Payment Receipt', 'x-bank-transfer' ) . ': ' . esc_url( $src ) . PHP_EOL;
				} else {
					echo '<p><strong>' . esc_html__( 'Bank Payment Receipt', 'x-bank-transfer' ) . ':</strong> <a href="' . esc_url( $src ) . '">' . esc_html__( 'View receipt', 'x-bank-transfer' ) . '</a></p>' . PHP_EOL;
				}
			}
		}
	}
}
