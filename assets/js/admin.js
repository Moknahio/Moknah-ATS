(function ($) {
	'use strict';
	const { __ } = wp.i18n;

	$(document).ready(function () {
		const generateBtn = $('.ats-generate-btn');
		const postId = generateBtn.data('post-id');
		const voiceSelect = $('#ats-voice-select');
		const audioEl = $('#ats-sample-audio');
		const sampleBox = $('#ats-voice-sample');

		let pollInterval = null;
		let countdownInterval = null;

		const RETRY_AFTER_SECONDS = 300; // 5 minutes
		const POLL_INTERVAL = 5000; // 5 seconds

		/**
		 * Format seconds to MM:SS
		 */
		function formatTime(seconds) {
			const m = Math.floor(seconds / 60);
			const s = seconds % 60;
			return `${m}:${s.toString().padStart(2, '0')}`;
		}

		/**
		 * Start retry countdown using _ats_moknah_started_at
		 */
		function startRetryCountdown(startedAt) {
			if (countdownInterval) {
				clearInterval(countdownInterval);
			}

			const retryAvailableAt = (startedAt + RETRY_AFTER_SECONDS) * 1000;

			function updateCountdown() {
				const now = Date.now();
				const remainingMs = Math.max(0, retryAvailableAt - now);
				const remainingSeconds = Math.ceil(remainingMs / 1000);

				if (remainingSeconds <= 0) {
					clearInterval(countdownInterval);
					unlockInterface('Regenerate Audio');
					generateBtn.css({ opacity: '1', cursor: 'pointer' });
					return;
				}

				generateBtn.text(`Retry in ${formatTime(remainingSeconds)}`);
			}

			lockInterface(`Retry in ${formatTime(RETRY_AFTER_SECONDS)}`);
			generateBtn.css({ opacity: '0.6', cursor: 'not-allowed' });
			updateCountdown();
			countdownInterval = setInterval(updateCountdown, 1000);
		}

		// -------------------------
		// Toast system
		// -------------------------
		const toastContainer = $('<div id="ats-toast-container"></div>').css({
			position: 'fixed', top: '20px', right: '20px', zIndex: 999999
		}).appendTo('body');

		function showToast(message, type = 'info', duration = 3000) {
			const colors = {
				success: '#4CAF50',
				error: '#F44336',
				info: '#2196F3'
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
			setTimeout(() => toast.css({ opacity: 1, transform: 'translateX(0)' }), 50);

			setTimeout(() => {
				toast.css({ opacity: 0, transform: 'translateX(100%)' });
				setTimeout(() => toast.remove(), 400);
			}, duration);

			toast.on('click', () => {
				toast.css({ opacity: 0, transform: 'translateX(100%)' });
				setTimeout(() => toast.remove(), 400);
			});
		}

		// -------------------------
		// Modal System
		// -------------------------
		function showConfirmModal(title, message, confirmText, onConfirm, onCancel) {
			$('#ats-confirm-modal').remove();

			const modalHtml =
				'<div id="ats-confirm-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">' +
				'<div class="ats-modal-box" style="background: #fff; padding: 32px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; width: 90%; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif; transform: scale(0.9); transition: transform 0.3s ease; box-sizing: border-box;">' +
				'<h3 style="margin: 0 0 16px; font-size: 20px; color: #1d2327; font-weight: 600;">' + title + '</h3>' +
				'<p style="margin: 0 0 28px; font-size: 15px; color: #50575e; line-height: 1.6;">' + message + '</p>' +
				'<div style="display: flex; justify-content: flex-end; gap: 12px;">' +
				'<button id="ats-modal-cancel" class="button button-secondary" style="padding: 8px 20px; border-radius: 4px; cursor: pointer;">Cancel</button>' +
				'<button id="ats-modal-confirm" class="button button-primary" style="padding: 8px 20px; border-radius: 4px; cursor: pointer;">' + confirmText + '</button>' +
				'</div>' +
				'</div>' +
				'</div>';

			$('body').append(modalHtml);

			const $modal = $('#ats-confirm-modal');
			const $modalBox = $modal.find('.ats-modal-box');

			setTimeout(function() {
				$modal.css('opacity', '1');
				$modalBox.css('transform', 'scale(1)');
			}, 10);

			function closeModal() {
				$modal.css('opacity', '0');
				$modalBox.css('transform', 'scale(0.9)');
				setTimeout(function() {
					$modal.remove();
				}, 300);
			}

			$('#ats-modal-cancel').on('click', function() {
				closeModal();
				if (typeof onCancel === 'function') onCancel();
			});

			$('#ats-modal-confirm').on('click', function() {
				closeModal();
				if (typeof onConfirm === 'function') onConfirm();
			});

			$modal.on('click', function(e) {
				if (e.target === this) {
					closeModal();
					if (typeof onCancel === 'function') onCancel();
				}
			});

			$modalBox.on('click', function(e) {
				e.stopPropagation();
			});
		}

		// -------------------------
		// Preprocessing Warning
		// -------------------------
		function showPreprocessingWarning(onProceed, onCancel) {
			const warningHtml =
				'<div style="padding: 16px; background: #fff8e5; border-left: 4px solid #ffa500; border-radius: 4px; margin-bottom: 20px;">' +
				'<p style="margin: 0; font-size: 14px; color: #744210; line-height: 1.6;">' +
				'<strong>⚠️ Arabic Language Only:</strong> Text preprocessing is optimized for Arabic language content only. ' +
				'Use it only if your article is in Arabic. For other languages, this may cause unexpected results.' +
				'</p>' +
				'</div>';

			showConfirmModal(
				'Enable Text Preprocessing?',
				warningHtml + '<p style="margin: 0; font-size: 15px; color: #50575e; line-height: 1.6;">Do you want to proceed with Arabic text preprocessing enabled?</p>',
				'Yes, Enable Preprocessing',
				onProceed,
				onCancel
			);
		}

		// -------------------------
		// UI Helpers
		// -------------------------
		function lockInterface(buttonText) {
			$('.ats-toggle input[type="checkbox"]').prop('disabled', true);
			voiceSelect.prop('disabled', true);
			generateBtn.prop('disabled', true).text(buttonText);
		}

		function unlockInterface(buttonText) {
			$('.ats-toggle input[type="checkbox"]').prop('disabled', false);
			voiceSelect.prop('disabled', false);
			generateBtn.prop('disabled', false).text(buttonText);
		}

		// -------------------------
		// Voice Sample Preview
		// -------------------------
		voiceSelect.on('change', function () {
			const sampleUrl = $(this).find(':selected').data('sample');

			if (sampleUrl) {
				if (audioEl.length) {
					audioEl.attr('src', sampleUrl);
					audioEl[0].load();
				}
				sampleBox.show();
			} else {
				if (audioEl.length) {
					audioEl.attr('src', '');
				}
				sampleBox.hide();
			}
		});

		// -------------------------
		// Polling Logic
		// -------------------------
		function checkAudioStatus() {
			if (!postId) return;

			$.ajax({
				url: atsmoknahAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'atsmoknah_get_audio',
					post_id: postId,
					nonce: atsmoknahAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						const status = response.data.status;
						const startedAt = response.data.started_at;

						// Show retry countdown if processing
						if (status === 'processing' || status === 'queued' || status === 'preprocessing') {
							if (startedAt && !countdownInterval) {
								startRetryCountdown(startedAt);
							}
						}
						// Completed
						else if (status === 'completed') {
							if (pollInterval) clearInterval(pollInterval);
							if (countdownInterval) clearInterval(countdownInterval);

							showToast('Audio generation completed successfully!', 'success', 4000);
							unlockInterface('Regenerate Audio');

							setTimeout(function() {
								location.reload();
							}, 1500);
						}
						// Failed
						else if (status === 'failed') {
							if (pollInterval) clearInterval(pollInterval);
							if (countdownInterval) clearInterval(countdownInterval);

							const details = response.data.details || 'Unknown error';
							showToast('Generation Failed: ' + details, 'error', 6000);
							unlockInterface('Regenerate Audio');

							setTimeout(function() {
								location.reload();
							}, 2500);
						}
					}
				},
				error: function() {
					// Network error - just retry on next poll
				}
			});
		}

		// Check if page loaded in processing state
		if (generateBtn.text().trim() === 'Processing...') {
			pollInterval = setInterval(checkAudioStatus, POLL_INTERVAL);
			checkAudioStatus(); // Immediate check
		}

		// -------------------------
		// Generate Button Click
		// -------------------------
		generateBtn.on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			if ($btn.prop('disabled')) return;

			const originalText = $btn.data('original-text') || $btn.text();
			$btn.data('original-text', originalText);

			const enabled = $('input[name="ats_moknah_enabled"]').is(':checked') ? '1' : '0';
			const preprocess = $('input[name="ats_moknah_preprocessing"]').is(':checked') ? '2' : '0';
			const voiceId = voiceSelect.val();

			if (preprocess === '2') {
				showPreprocessingWarning(
					function() {
						showMainConfirmation();
					},
					function() {
						// Cancelled
					}
				);
			} else {
				showMainConfirmation();
			}

			function showMainConfirmation() {
				showConfirmModal(
					__('Confirm Audio Generation', 'ats-moknah-article-to-speech'),
					'Are you sure the generation settings are correct and you want to proceed? This will process your article and may take a few moments.',
					'Yes, Generate Audio',
					function() {
						$btn.prop('disabled', true);
						lockInterface('Starting...');

						$.ajax({
							url: atsmoknahAdmin.ajaxUrl,
							type: 'POST',
							data: {
								action: 'atsmoknah_generate',
								nonce: atsmoknahAdmin.nonce,
								post_id: postId,
								ats_moknah_enabled: enabled,
								ats_moknah_preprocessing: preprocess,
								ats_moknah_voice_id: voiceId
							},
							success: function(response) {
								if (response.success) {
									showToast(response.data.message, 'info');
									generateBtn.text('Processing...');

									if (!pollInterval) {
										pollInterval = setInterval(checkAudioStatus, POLL_INTERVAL);
										checkAudioStatus();
									}
								} else {
									showError(response.data.message || 'An unknown error occurred');
								}
							},
							error: function(xhr) {
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
							}
						});

						function showError(message) {
							if (pollInterval) clearInterval(pollInterval);
							if (countdownInterval) clearInterval(countdownInterval);

							showToast(message, 'error', 5000);
							unlockInterface(originalText);
							$btn.prop('disabled', false);
						}
					},
					function() {
						$btn.prop('disabled', false);
					}
				);
			}
		});

	});

})(jQuery);