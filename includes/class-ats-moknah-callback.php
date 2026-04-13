<?php
namespace ATS_Moknah;

if (!defined('ABSPATH')) exit;
class Callback {

    public static function register() {
        add_action('rest_api_init', function () {
            register_rest_route('ats-moknah/v1', '/callback', [
                'methods' => 'POST',
                'callback' => [self::class, 'handle'],
                'permission_callback' => [self::class, 'validateCallback'] // Validate HMAC signature should be used for production
            ]);
        });

    }

    public static function ajaxGetAudioUrl(): void
    {
        try {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            check_ajax_referer('ats_moknah_ajax', 'nonce');

            // Accept both 'post_id' and 'id' to be safe
            $postId = intval($_POST['post_id'] ?? $_POST['id'] ?? 0);
            if (!$postId) {-
                wp_send_json_error(['message' => 'Invalid ID'], 400);
            }

            $audio   = get_post_meta($postId, '_ats_moknah_audio_url', true);
            $srt     = get_post_meta($postId, '_ats_moknah_srt_url', true);
            $status  = get_post_meta($postId, '_ats_moknah_status', true);
            $details = get_post_meta($postId, '_ats_moknah_status_details', true);
            $started_at = get_post_meta($postId, '_ats_moknah_started_at', true);

            wp_send_json_success([
                'audio'   => $audio ?: '',
                'srt'     => $srt ?: '',
                'status'  => $status ?: '',
                'started_at' => (int) $started_at,
                'details' => $details ?: ''
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate callback request
     */
    public static function validateCallback($request) {
        $apiKey = get_option('ats_moknah_api_key');

        if (empty($apiKey)) {
            return new \WP_Error('unauthorized', 'API key not configured', ['status' => 401]);
        }

        $data = $request->get_json_params();
        $postId   = $data['articleId'] ?? null;
        $audioUrl = $data['response']['audioFile'] ?? null;
        $srtUrl   = $data['response']['srtFile'] ?? null;
        $signature = $data['response']['signature'] ?? null;

        if (!$postId || !$audioUrl || !$srtUrl || !$signature) {
            return false;
        }

        $post = get_post($postId);
        if (!$post) {
            return false;
        }

        $apiKeyHash = hash('sha256', $apiKey, false); // SHA256 hash
        $payload = $postId . '|' . $audioUrl . '|' . $srtUrl;
        $expectedSignature = hash_hmac('sha256', $payload, $apiKeyHash);
        if (!hash_equals($expectedSignature, $signature)) {
            return new \WP_Error('unauthorized', 'Invalid signature', ['status' => 401]);
        }

        return true;
    }


    public static function handle($request) {
        try {
            $data = $request->get_json_params();

            // Validate required fields
            $postId = $data['articleId'] ?? null;
            $response = $data['response'] ?? [];
            $status = $data['status'] ?? 'unknown';

            if (!$postId) {
                throw new \Exception('Missing articleId in callback data');
            }

            $post = get_post($postId);
            if (!$post) {
                throw new \Exception('Post not found: ' . intval($postId));
            }

            // Handle success case
            if ($status === 'completed' || $status === 'success' || isset($response['audioFile'])) {
                self::handleSuccess($postId, $post, $response);
                return ['status' => 'success', 'message' => 'Callback processed successfully'];
            }

            // Handle failure case
            if ($status === 'failed' || $status === 'error') {
                self::handleFailure($postId, $post, $data);
                return ['status' => 'success', 'message' => 'Failure callback processed'];
            }

            // Handle unknown status
            update_post_meta($postId, '_ats_moknah_status', 'unknown');
            update_post_meta($postId, '_ats_moknah_status_details', 'Received unknown status from Moknah API: ' . sanitize_text_field($status));

            return ['status' => 'warning', 'message' => 'Unknown callback status'];

        } catch (\Exception $e) {

            if (isset($postId) && $postId) {
                update_post_meta($postId, '_ats_moknah_status', 'callback_error');
                update_post_meta($postId, '_ats_moknah_status_details', 'Error processing callback: ' . $e->getMessage());
            }

            return new \WP_Error(
                'callback_error',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }

    /**
     * Handle successful audio generation
     */
    private static function handleSuccess($postId, $post, $response) {
        $audioUrl = $response['audioFile'] ?? null;
        $srtUrl = $response['srtFile'] ?? null;
        $duration = $response['duration'] ?? null;
        $wordCount = $response['wordCount'] ?? null;

        if (!$audioUrl) {
            throw new \Exception('Audio file URL missing in success response');
        }

        // Validate audio URL
        if (!filter_var($audioUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid audio file URL received: ' . esc_url_raw($audioUrl));
        }

        // Update post meta
        update_post_meta($postId, '_ats_moknah_audio_url', esc_url_raw($audioUrl));

        if ($srtUrl && filter_var($srtUrl, FILTER_VALIDATE_URL)) {
            update_post_meta($postId, '_ats_moknah_srt_url', esc_url_raw($srtUrl));
        }

        if ($duration) {
            update_post_meta($postId, '_ats_moknah_audio_duration', sanitize_text_field($duration));
        }

        if ($wordCount) {
            update_post_meta($postId, '_ats_moknah_word_count', intval($wordCount));
        }

        update_post_meta($postId, '_ats_moknah_status', 'completed');
        update_post_meta($postId, '_ats_moknah_status_details', 'Audio generated successfully on ' . current_time('F j, Y \a\t g:i a'));
        update_post_meta($postId, '_ats_moknah_completed_at', current_time('mysql'));
        if (function_exists('do_action')) {
            if (defined('LSCWP_V')) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party LiteSpeed Cache hook.
                do_action('litespeed_purge_post', $postId);
            }
        }
    }

    /**
     * Handle failed audio generation
     */
    private static function handleFailure($postId, $post, $data) {
        $errorMessage = $data['error'] ?? $data['message'] ?? 'Audio generation failed';
        $errorDetails = $data['details'] ?? '';

        $fullError = $errorMessage;
        if ($errorDetails) {
            $fullError .= ' - ' . $errorDetails;
        }

        update_post_meta($postId, '_ats_moknah_status', 'failed');
        update_post_meta($postId, '_ats_moknah_status_details', sanitize_text_field($fullError));
        update_post_meta($postId, '_ats_moknah_failed_at', current_time('mysql'));

    }
}