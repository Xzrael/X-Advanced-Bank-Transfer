/* global jQuery, xbtCheckout */
(function ($) {
	'use strict';

	var STORAGE_KEY = 'xbt_receipt_attach_id';

	function setStatus(el, message, isError) {
		if (!el) {
			return;
		}
		el.text(message || '');
		el.css('color', isError ? '#d63638' : '#00a32a');
	}

	function getStoredAttachId() {
		try {
			return window.sessionStorage.getItem(STORAGE_KEY) || '';
		} catch (e) {
			return '';
		}
	}

	function storeAttachId(id) {
		try {
			if (id) {
				window.sessionStorage.setItem(STORAGE_KEY, id);
			} else {
				window.sessionStorage.removeItem(STORAGE_KEY);
			}
		} catch (e) { /* sessionStorage unavailable - ignore */ }
	}

	// WooCommerce re-renders the payment box (fragments) on checkout updates,
	// which wipes the hidden field. Restore it from storage every time.
	function restoreAttachId() {
		var id = getStoredAttachId();
		var $attach = $('#xbt_attach_id');

		if (!$attach.length || !id) {
			return;
		}

		if (!$attach.val()) {
			$attach.val(id);
			setStatus($('#xbt_upload_status'), 'Receipt attached. You can replace it by choosing a new file.', false);
		}
	}

	$(document).ready(function () {
		// Keep the receipt through checkout updates and payment-method re-renders.
		$(document.body).on('updated_checkout init_checkout', restoreAttachId);

		// Guarantee the receipt is submitted even if the field was cleared at the
		// last moment: fire right before the gateway order is placed.
		$(document.body).on('checkout_place_order_xbt_gateway', function () {
			var id = getStoredAttachId();
			if (id) {
				$('#xbt_attach_id').val(id);
			}
			return true;
		});

		restoreAttachId();

		$(document.body).on('change', 'input.bank_payment_receipt', function () {
			var $input = $(this);
			var file = $input[0] && $input[0].files ? $input[0].files[0] : null;
			var $status = $('#xbt_upload_status');
			var $attach = $('#xbt_attach_id');

			if (!$input.closest('form').length) {
				return;
			}

			if (!file) {
				setStatus($status, '', false);
				return;
			}

			// Quick client-side extension check for better UX.
			var allowed = ['jpg', 'jpeg', 'png', 'pdf'];
			var ext = (file.name.split('.').pop() || '').toLowerCase();
			if (allowed.indexOf(ext) === -1) {
				setStatus($status, xbtCheckout.text.invalidFile, true);
				$attach.val('');
				storeAttachId('');
				$input.val('');
				return;
			}

			var fd = new FormData();
			fd.append('file', file);
			fd.append('action', 'xbt_upload_receipt');
			fd.append('nonce', xbtCheckout.nonce);

			setStatus($status, 'Uploading receipt...', false);

			$.ajax({
				type: 'POST',
				url: xbtCheckout.ajaxUrl,
				data: fd,
				contentType: false,
				processData: false,
				success: function (response) {
					if (response && response.success) {
						$attach.val(response.data);
						storeAttachId(response.data);
						setStatus($status, 'Receipt uploaded successfully.', false);
					} else {
						var msg = (response && response.data && response.data.message)
							? response.data.message
							: xbtCheckout.text.defaultError;
						$attach.val('');
						storeAttachId('');
						$input.val('');
						setStatus($status, msg, true);
					}
				},
				error: function () {
					$attach.val('');
					storeAttachId('');
					$input.val('');
					setStatus($status, xbtCheckout.text.defaultError, true);
				}
			});
		});
	});
})(jQuery);