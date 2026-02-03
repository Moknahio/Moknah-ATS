(function ($) {
	'use strict';

	$(document).ready(function () {

		const voiceSelect = $('#ats-voice-select');
		const audioEl = $('#ats-sample-audio');
		const sampleBox = $('#ats-voice-sample');

		// -------------------------
		// Toast system (all JS)
		// -------------------------
		const toastContainer = $('<div id="ats-toast-container"></div>').css({
			position: 'fixed', top: '20px', right: '20px', zIndex: 9999
		}).appendTo('body');

		function showToast(message, type = 'info', duration = 3000) {
			const colors = {
				success: '#4CAF50', error: '#F44336', info: '#2196F3'
			};

			const toast = $('<div></div>').text(message).css({
				minWidth: '250px',
				marginBottom: '10px',
				padding: '12px 20px',
				borderRadius: '8px',
				color: '#fff',
				fontWeight: 500,
				boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
				opacity: 0,
				transform: 'translateX(100%)',
				transition: 'all 0.4s ease',
				cursor: 'pointer',
				backgroundColor: colors[type] || colors.info
			});

			toastContainer.append(toast);

			setTimeout(() => toast.css({opacity: 1, transform: 'translateX(0)'}), 50);

			// Auto-remove
			setTimeout(() => {
				toast.css({opacity: 0, transform: 'translateX(100%)'});
				setTimeout(() => toast.remove(), 400);
			}, duration);

			// Click to dismiss
			toast.on('click', () => {
				toast.css({opacity: 0, transform: 'translateX(100%)'});
				setTimeout(() => toast.remove(), 400);
			});
		}

		// -------------------------
		// Update voice sample
		// -------------------------
		voiceSelect.on('change', function () {
			const sampleUrl = $(this).find(':selected').data('sample');

			if (sampleUrl && audioEl.length) {
				audioEl.attr('src', sampleUrl);
				audioEl[0].load();
				sampleBox.show();
			} else {
				audioEl.attr('src', '');
				sampleBox.hide();
			}
		});

		// -------------------------
		// Generate TTS with checkbox check
		// -------------------------
		$('.ats-generate-btn').on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const postId = $btn.data('post-id');
			const enabled = $('input[name="ats_moknah_enabled"]').is(':checked') ? '1' : '0';
			const preprocess = $('input[name="ats_moknah_preprocessing"]').is(':checked') ? '2' : '0';
			const voiceId = $('#ats-voice-select').val();
			$btn.prop('disabled', true).text('Generating...');
			$.ajax({
				url: atsMoknah.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ats_moknah_generate',
					nonce: atsMoknah.nonce,
					post_id: postId,
					ats_moknah_enabled: enabled,
					ats_moknah_preprocessing: preprocess,
					ats_moknah_voice_id: voiceId
				},
				success: function(response) {
					if (response.success) {
						showToast(response.data.message, 'success');

						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						showError(response.data.message || 'An unknown error occurred');
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
					} else if (xhr.status === 403) {
						errorMsg = 'Permission denied. Please refresh the page and try again.';
					}

					showError(errorMsg);
				},
				complete: function() {
					$btn.prop('disabled', false).text($btn.data('original-text') || 'Generate Audio');
				}
			});

			function showError(message) {
				showToast(message, 'error');
				$btn.prop('disabled', false);
			}
		});

		// Voice sample preview
		$('#ats-voice-select').on('change', function() {
			const sample = $(this).find(':selected').data('sample');
			const $audio = $('#ats-sample-audio');

			if (sample && $audio.length) {
				$audio.attr('src', sample);
			}
		});



	});

})(jQuery);
