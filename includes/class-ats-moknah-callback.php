<?php
namespace ATS_Moknah;

class Callback {

    public static function register() {
        add_action('rest_api_init', function () {
            register_rest_route('ats-moknah/v1', '/callback', [
                'methods' => 'POST',
                'callback' => [self::class, 'handle'],
                'permission_callback' => [self::class, 'validateCallback'] // Validate HMAC signature should be used for production
//                'permission_callback' => '__return_true' // For testing only, disable in production
            ]);
        });
    }

    /**
     * Validate callback request
     */
    public static function validateCallback($request) {
        $apiKey = get_option('ats_moknah_api_key');

        if (empty($apiKey)) {
            error_log('[ATS Moknah Callback] API key not configured');
            return new \WP_Error('unauthorized', 'API key not configured', ['status' => 401]);
        }

        $data = $request->get_json_params();
        $postId   = $data['articleId'] ?? null;
        $audioUrl = $data['response']['audioFile'] ?? null;
        $srtUrl   = $data['response']['srtFile'] ?? null;
        $signature = $data['response']['signature'] ?? null;

        if (!$postId || !$audioUrl || !$srtUrl || !$signature) {
            error_log('[ATS Moknah Callback] Missing required fields');
            return false;
        }

        $post = get_post($postId);
        if (!$post) {
            error_log('[ATS Moknah Callback] Post not found: ' . $postId);
            return false;
        }

        $enabled = get_post_meta($postId, '_ats_moknah_enabled', true);
        if ($enabled !== '1') {
            error_log('[ATS Moknah Callback] TTS not enabled for post: ' . $postId);
            return false;
        }

        $apiKeyHash = hash('sha256', $apiKey, false); // SHA256 hash
        $payload = $postId . '|' . $audioUrl . '|' . $srtUrl;
        $expectedSignature = hash_hmac('sha256', $payload, $apiKeyHash);
        if (!hash_equals($expectedSignature, $signature)) {
            error_log('[ATS Moknah Callback] Invalid HMAC signature');
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
                throw new \Exception('Post not found: ' . $postId);
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
            error_log('[ATS Moknah Callback] Unknown status received: ' . $status);
            update_post_meta($postId, '_ats_moknah_status', 'unknown');
            update_post_meta($postId, '_ats_moknah_status_details', 'Received unknown status from Moknah API: ' . $status);

            return ['status' => 'warning', 'message' => 'Unknown callback status'];

        } catch (\Exception $e) {
            error_log('[ATS Moknah Callback Error] ' . $e->getMessage());

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
            throw new \Exception('Invalid audio file URL received: ' . $audioUrl);
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

        error_log('[ATS Moknah Callback] Successfully processed audio for post ID ' . $postId);
        // Send notification to post author
        self::notifyAuthor($post, $audioUrl);

        // Send admin notification if enabled
        self::notifyAdmin($post, $audioUrl);
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

        error_log('[ATS Moknah Callback] Audio generation failed for post ID ' . $postId . ': ' . $fullError);

        // Notify author of failure
        self::notifyAuthorFailure($post, $fullError);

        // Notify admin of failure
        self::notifyAdminFailure($post, $fullError);
    }

    /**
     * Send email notification to post author
     */
    private static function notifyAuthor($post, $audioUrl) {
        // Check if author notifications are enabled
        if (get_option('ats_moknah_notify_author', '1') !== '1') {
            return;
        }

        $author = get_userdata($post->post_author);

        if (!$author || !$author->user_email) {
            error_log('[ATS Moknah Callback] Cannot send notification - author not found for post ' . $post->ID);
            return;
        }

        $postTitle = get_the_title($post->ID);
        $postUrl = get_edit_post_link($post->ID, 'raw');
        $siteName = get_bloginfo('name');

        $subject = sprintf('[%s] Audio Generated: %s', $siteName, $postTitle);

        $message = sprintf(
            "Hi %s,\n\n" .
            "Great news! The audio version of your post is now ready.\n\n" .
            "Post: %s\n" .
            "Audio URL: %s\n\n" .
            "You can listen to it or make changes here:\n%s\n\n" .
            "---\n" .
            "This is an automated notification from ATS Moknah plugin.",
            $author->display_name,
            $postTitle,
            $audioUrl,
            $postUrl
        );

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $siteName . ' <' . get_option('admin_email') . '>'
        ];

        $sent = wp_mail($author->user_email, $subject, $message, $headers);

        if ($sent) {
            error_log('[ATS Moknah Callback] Email notification sent to author for post ' . $post->ID);
        } else {
            error_log('[ATS Moknah Callback] Failed to send email notification to author for post ' . $post->ID);
        }
    }

    /**
     * Send failure notification to post author
     */
    private static function notifyAuthorFailure($post, $errorMessage) {
        if (get_option('ats_moknah_notify_failures', '1') !== '1') {
            return;
        }

        // Check if author notifications are enabled
        if (get_option('ats_moknah_notify_author', '1') !== '1') {
            return;
        }

        $author = get_userdata($post->post_author);

        if (!$author || !$author->user_email) {
            return;
        }

        $postTitle = get_the_title($post->ID);
        $postUrl = get_edit_post_link($post->ID, 'raw');
        $siteName = get_bloginfo('name');

        $subject = sprintf('[%s] Audio Generation Failed: %s', $siteName, $postTitle);

        $message = sprintf(
            "Hi %s,\n\n" .
            "Unfortunately, the audio generation for your post failed.\n\n" .
            "Post: %s\n" .
            "Error: %s\n\n" .
            "You can try generating the audio again here:\n%s\n\n" .
            "If the problem persists, please contact the site administrator.\n\n" .
            "---\n" .
            "This is an automated notification from ATS Moknah plugin.",
            $author->display_name,
            $postTitle,
            $errorMessage,
            $postUrl
        );

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $siteName . ' <' . get_option('admin_email') . '>'
        ];

        wp_mail($author->user_email, $subject, $message, $headers);
    }

    /**
     * Send admin notification
     */
    private static function notifyAdmin($post, $audioUrl) {
        // Only notify admin if it's a different user than the author
        if (get_option('ats_moknah_notify_admin') !== '1') {
            return;
        }
        $adminEmail = get_option('admin_email');
        $author = get_userdata($post->post_author);

        if ($author && $author->user_email === $adminEmail) {
            return; // Don't send duplicate notification
        }

        $postTitle = get_the_title($post->ID);
        $postUrl = get_edit_post_link($post->ID, 'raw');
        $siteName = get_bloginfo('name');
        $authorName = $author ? $author->display_name : 'Unknown';

        $subject = sprintf('[%s] Audio Generated for: %s', $siteName, $postTitle);

        $message = sprintf(
            "Audio generation completed successfully.\n\n" .
            "Post: %s\n" .
            "Author: %s\n" .
            "Audio URL: %s\n\n" .
            "Edit post: %s\n\n" .
            "---\n" .
            "ATS Moknah notification",
            $postTitle,
            $authorName,
            $audioUrl,
            $postUrl
        );

        wp_mail($adminEmail, $subject, $message);
    }

    /**
     * Send admin failure notification
     */
    private static function notifyAdminFailure($post, $errorMessage) {
        if (get_option('ats_moknah_notify_failures', '1') !== '1') {
            return;
        }

        // Check if admin notifications are enabled
        if (get_option('ats_moknah_notify_admin') !== '1') {
            return;
        }
        $adminEmail = get_option('admin_email');
        $author = get_userdata($post->post_author);

        if ($author && $author->user_email === $adminEmail) {
            return;
        }

        $postTitle = get_the_title($post->ID);
        $postUrl = get_edit_post_link($post->ID, 'raw');
        $siteName = get_bloginfo('name');
        $authorName = $author ? $author->display_name : 'Unknown';

        $subject = sprintf('[%s] Audio Generation Failed: %s', $siteName, $postTitle);

        $message = sprintf(
            "Audio generation failed.\n\n" .
            "Post: %s\n" .
            "Author: %s\n" .
            "Error: %s\n\n" .
            "Edit post: %s\n\n" .
            "---\n" .
            "ATS Moknah notification",
            $postTitle,
            $authorName,
            $errorMessage,
            $postUrl
        );

        wp_mail($adminEmail, $subject, $message);
    }

    /**
     * Get user-friendly duration format
     */
    private static function formatDuration($seconds) {
        if (!is_numeric($seconds)) {
            return $seconds;
        }

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d:%02d', $minutes, $seconds);
        }

        return sprintf('0:%02d', $seconds);
    }
}