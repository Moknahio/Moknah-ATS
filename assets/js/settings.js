jQuery(document).ready(function($) {
	// Test Email button handler
	$('#ats-test-email-btn').on('click', function(e) {
		e.preventDefault();

		const $btn = $(this);
		const $result = $('#ats-test-email-result');
		const originalText = $btn.html();

		// Disable button and show loading state
		$btn.prop('disabled', true)
			.html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top: 3px;"></span> Sending...');

		$result.removeClass('success error').hide();

		$.ajax({
			url: atsMoknahSettings.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ats_moknah_test_email',
				nonce: atsMoknahSettings.nonce
			},
			success: function(response) {
				if (response.success) {
					$result
						.addClass('success')
						.html(
							'<div class="ats-test-email-success">' +
							'<span class="dashicons dashicons-yes-alt"></span> ' +
							'<strong>Success!</strong> ' + response.data.message +
							'</div>'
						)
						.fadeIn();
				} else {
					showError(response.data.message || 'Failed to send test email');
				}
			},
			error: function(xhr, status, error) {
				let errorMsg = 'Network error occurred. Please try again.';

				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMsg = xhr.responseJSON.data.message;
				} else if (xhr.status === 0) {
					errorMsg = 'No connection. Please check your internet connection.';
				} else if (xhr.status === 500) {
					errorMsg = 'Server error. Please try again later.';
				}

				showError(errorMsg);
			},
			complete: function() {
				$btn.prop('disabled', false).html(originalText);
			}
		});

		function showError(message) {
			$result
				.addClass('error')
				.html(
					'<div class="ats-test-email-error">' +
					'<span class="dashicons dashicons-warning"></span> ' +
					'<strong>Error:</strong> ' + message +
					'</div>'
				)
				.fadeIn();
		}
	});
});