<?php

namespace ATS_Moknah;

// Prevent direct file access
if (!defined('ABSPATH')) exit;

function ats_moknah_sanitize_checkbox($input)
{
    return ($input === '1') ? '1' : '0';
}

class Admin
{
    private static $errors = [];
    private static $notices = [];

    public static function getVoices(): array
    {
        $voices = get_transient('ats_moknah_voices');

        if ($voices === false || !is_array($voices) || empty($voices)) {
            $result = voice_list();

            if (isset($result['error'])) {
                self::addError($result['error']);
                return [];
            }

            $voices = $result;

            if (!empty($voices) && is_array($voices)) {
                set_transient('ats_moknah_voices', $voices, 360000);
            } else {
                self::addError(__('Failed to load voices. Please check your API key and try again.', 'ats-moknah'));
            }
        }

        return $voices ?: [];
    }

    public static function register()
    {
        add_action('admin_menu', function () {
            add_menu_page(
                __('ATS Moknah', 'ats-moknah'),
                __('ATS Moknah', 'ats-moknah'),
                'manage_options',
                'ats-moknah',
                [self::class, 'settingsPage'],
                'dashicons-format-audio'
            );
        });

        add_action('admin_init', function () {

            // Text fields
            register_setting('ats_moknah_settings', 'ats_moknah_api_key', [
                'sanitize_callback' => 'sanitize_text_field'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_callback_url', [
                'sanitize_callback' => 'esc_url_raw'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_voice_id', [
                'sanitize_callback' => 'sanitize_text_field'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_article_selector', [
                'sanitize_callback' => 'sanitize_text_field'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_skipped_selectors', [
                'sanitize_callback' => 'sanitize_text_field'
            ]);

            // Checkbox/boolean notifications
            register_setting('ats_moknah_settings', 'ats_moknah_notify_author', [
                'sanitize_callback' => __NAMESPACE__ . '\\ats_moknah_sanitize_checkbox'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_notify_admin', [
                'sanitize_callback' => __NAMESPACE__ . '\\ats_moknah_sanitize_checkbox'
            ]);
            register_setting('ats_moknah_settings', 'ats_moknah_notify_failures', [
                'sanitize_callback' => __NAMESPACE__ . '\\ats_moknah_sanitize_checkbox'
            ]);

        });

        add_action('add_meta_boxes', [self::class, 'registerMetaBoxes']);
        add_action('save_post', [self::class, 'savePostMeta'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdminAssets']);
        add_action('wp_ajax_ats_moknah_generate', [self::class, 'ajaxGenerate']);
        add_action('wp_ajax_ats_moknah_test_email', [self::class, 'ajaxTestEmail']);
        add_action('admin_notices', [self::class, 'displayAdminNotices']);
    }

    public static function displayAdminNotices()
    {
        foreach (self::$errors as $error) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__('ATS Moknah:', 'ats-moknah') . '</strong> ' . esc_html($error) . '</p></div>';
        }

        foreach (self::$notices as $notice) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('ATS Moknah:', 'ats-moknah') . '</strong> ' . esc_html($notice) . '</p></div>';
        }
    }

    private static function addError($message)
    {
        self::$errors[] = $message;
    }

    private static function addNotice($message)
    {
        self::$notices[] = $message;
    }

    /**
     * AJAX handler for testing email configuration
     */
    public static function ajaxTestEmail()
    {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to test email settings.', 'ats-moknah'));
            }

            if (!check_ajax_referer('ats_moknah_settings_nonce', 'nonce', false)) {
                throw new \Exception(__('Security verification failed. Please refresh the page and try again.', 'ats-moknah'));
            }

            $currentUser = wp_get_current_user();
            $adminEmail = get_option('admin_email');
            $siteName = get_bloginfo('name');
            $siteUrl = get_bloginfo('url');

            $to = $currentUser->user_email;
            $subject = sprintf(__('[%s] Email Test - ATS Moknah', 'ats-moknah'), $siteName);

            $messageTemplate = __(
                "This is a test email from ATS Moknah plugin.\n\n" .
                "Site: %s\n" .
                "Site URL: %s\n" .
                "Sent to: %s\n" .
                "Sent at: %s\n\n" .
                "If you received this email, your WordPress email configuration is working correctly.\n\n" .
                "Email notifications will be sent when:\n" .
                "• Audio generation completes successfully\n" .
                "• Audio generation fails (if enabled)\n\n" .
                "You can configure notification preferences in the ATS Moknah settings.\n\n" .
                "---\n" .
                "ATS Moknah Plugin Test Email",
                $siteName,
                $siteUrl,
                $to,
                current_time('F j, Y \a\t g:i a')
            );

            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . $siteName . ' <' . $adminEmail . '>'
            ];

            // Capture potential PHP mail errors
            $phpmailer_error = '';
            add_action('wp_mail_failed', function ($wp_error) use (&$phpmailer_error) {
                $phpmailer_error = $wp_error->get_error_message();
            });

            $sent = wp_mail($to, $subject, $message, $headers);

            if (!$sent) {
                $error_details = $phpmailer_error ?: __('Unknown error', 'ats-moknah');

                // Provide helpful troubleshooting information
                $troubleshooting = self::getEmailTroubleshooting();

                /* translators: 1: Error details from PHPMailer, 2: Additional troubleshooting info */
                throw new \Exception(
                    sprintf(
                        __('Failed to send test email. Error: %1$s. Please check your email configuration. %2$s', 'ats-moknah'),
                        $error_details,
                        $troubleshooting
                    )
                );
            }

            wp_send_json_success([
                /* translators: %s: Email address the test email was sent to */
                'message' => sprintf(
                    __('Test email sent successfully to %s. Please check your inbox (and spam folder).', 'ats-moknah'),
                    $to
                ),
                'email' => $to,
            ]);


        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'email_test_failed'
            ]);
        }
    }

    /**
     * Get email troubleshooting information
     */
    private static function getEmailTroubleshooting()
    {
        $tips = [];

        // Check if using default PHP mail
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $tips[] = __('Your server may not be configured to send emails.', 'ats-moknah');
        }

        // Common solutions
        $tips[] = __('Try installing an SMTP plugin like "WP Mail SMTP" or "Easy WP SMTP".', 'ats-moknah');
        $tips[] = __('Contact your hosting provider to verify email sending is enabled.', 'ats-moknah');

        return implode(' ', $tips);
    }

    public static function ajaxGenerate()
    {
        try {
            if (!current_user_can('edit_posts')) {
                throw new \Exception(__('You do not have permission to generate audio for posts.', 'ats-moknah'));
            }

            if (!check_ajax_referer('ats_moknah_ajax', 'nonce', false)) {
                throw new \Exception(__('Security verification failed. Please refresh the page and try again.', 'ats-moknah'));
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                throw new \Exception(__('Invalid post ID provided.', 'ats-moknah'));
            }

            $post = get_post($post_id);
            if (!$post) {
                throw new \Exception(__('Post not found. It may have been deleted.', 'ats-moknah'));
            }

            $enabled = isset($_POST['ats_moknah_enabled']) ? sanitize_text_field(wp_unslash($_POST['ats_moknah_enabled'])) : '0';
            update_post_meta($post_id, '_ats_moknah_enabled', $enabled);

            $preprocess = isset($_POST['ats_moknah_preprocessing']) ? sanitize_text_field(wp_unslash($_POST['ats_moknah_preprocessing'])) : '1';
            update_post_meta($post_id, '_ats_moknah_preprocessing', $preprocess);

            $voice = isset($_POST['ats_moknah_voice_id']) ? sanitize_text_field(wp_unslash($_POST['ats_moknah_voice_id'])) : '';

            if ($voice) {
                $voices = self::getVoices();
                if (empty($voices)) {
                    throw new \Exception(__('Unable to load available voices. Please check your API key in settings.', 'ats-moknah'));
                }

                if (!array_key_exists($voice, $voices)) {
                    throw new \Exception(__('Selected voice is not available. Please choose a different voice.', 'ats-moknah'));
                }

                update_post_meta($post_id, '_ats_moknah_voice_id', $voice);
            }

            if ($enabled !== '1') {
                throw new \Exception(__('Please enable Text-to-Speech before generating audio.', 'ats-moknah'));
            }

            $api_key = get_option('ats_moknah_api_key');
            if (empty($api_key)) {
                throw new \Exception(__('API key not configured. Please add your Moknah API key in settings.', 'ats-moknah'));
            }

            $content = trim(wp_strip_all_tags(strip_shortcodes($post->post_content)));
            if (empty($content)) {
                throw new \Exception(__('Post content is empty. Please add content before generating audio.', 'ats-moknah'));
            }

            if (strlen($content) < 50) {
                throw new \Exception(__('Post content is too short. Please add at least 50 characters of content.', 'ats-moknah'));
            }

            self::generateTTS($post_id);
            if (get_option('ats_moknah_notify_author', '1') !== '1') {
                self::addNotice(__('Audio generation started. Notifications to post author are disabled.', 'ats-moknah'));
                wp_send_json_success([
                    'message' => __('Audio generation started successfully. Notifications to post author are disabled.', 'ats-moknah'),
                    'status' => 'processing'
                ]);
            } else {
                wp_send_json_success([
                    'message' => __('Audio generation started successfully. You will be notified when it\'s ready.', 'ats-moknah'),
                    'status' => 'processing'
                ]);
            }

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'generation_failed'
            ]);
        }
    }

    public static function enqueueAdminAssets($hook)
    {
        if ($hook === 'toplevel_page_ats-moknah') {
            wp_enqueue_style('ats-moknah-admin', plugin_dir_url(__FILE__) . '../assets/admin.css', [], '1.0');
            wp_enqueue_script('ats-moknah-settings', plugin_dir_url(__FILE__) . '../assets/settings.js', ['jquery'], '1.0', true);
            wp_localize_script('ats-moknah-settings', 'atsMoknahSettings', [
                'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ats_moknah_settings_nonce')
            ]);
        }

        wp_enqueue_style('ats-moknah-admin', plugin_dir_url(__FILE__) . '../assets/admin.css', [], '1.0');

        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('ats-moknah-admin', plugin_dir_url(__FILE__) . '../assets/admin.js', ['jquery'], '1.0', true);
            wp_localize_script('ats-moknah-admin', 'atsMoknah', [
                'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                'nonce' => wp_create_nonce('ats_moknah_ajax'),
                'voices' => self::getVoices(),
                'errors' => self::$errors
            ]);
        }
    }

    public static function registerMetaBoxes()
    {
        add_meta_box(
            'ats-moknah-controls',
            __('ATS Options', 'ats-moknah'),
            [self::class, 'renderControlsBox'],
            'post',
            'side',
            'high'
        );
    }

    public static function renderControlsBox($post)
    {
        wp_nonce_field('ats_moknah_meta', 'ats_moknah_nonce');

        $enabled = get_post_meta($post->ID, '_ats_moknah_enabled', true) === '1';
        $preprocessing = get_post_meta($post->ID, '_ats_moknah_preprocessing', true) === '1';
        $voice = get_post_meta($post->ID, '_ats_moknah_voice_id', true);
        $audioUrl = get_post_meta($post->ID, '_ats_moknah_audio_url', true);
        $status = get_post_meta($post->ID, '_ats_moknah_status', true);
        $status_details = get_post_meta($post->ID, '_ats_moknah_status_details', true);
        $defaultVoice = get_option('ats_moknah_voice_id');
        $current = $voice ?: $defaultVoice;
        $buttonLabel = $audioUrl ? __('Regenerate Audio', 'ats-moknah') : __('Generate Audio', 'ats-moknah');

        $voices = self::getVoices();
        $hasVoices = !empty($voices);
        $hasApiKey = !empty(get_option('ats_moknah_api_key'));
        ?>

        <div class="ats-moknah-meta-box">

            <?php if (!$hasApiKey) : ?>
                <div class="notice notice-error inline" style="margin: 0 0 15px 0; padding: 10px;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('API Key Required:', 'ats-moknah'); ?></strong>
                        <?php
                        /* translators: %s: link to the settings page */
                        printf(
                            esc_html__('Please configure your Moknah API key in %s first.', 'ats-moknah'),
                            '<a href="' . esc_url(admin_url('admin.php?page=ats-moknah')) . '">' . esc_html__('settings', 'ats-moknah') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php elseif (!$hasVoices) : ?>
                <div class="notice notice-warning inline" style="margin: 0 0 15px 0; padding: 10px;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('No Voices Available:', 'ats-moknah'); ?></strong>
                        <?php
                        /* translators: %s: link to the settings page */
                        printf(
                            esc_html__('Unable to load voices. Please check your API key in %s.', 'ats-moknah'),
                            '<a href="' . esc_url(admin_url('admin.php?page=ats-moknah')) . '">' . esc_html__('settings', 'ats-moknah') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>


            <?php if ($status === 'failed' && $status_details): ?>
                <div class="notice notice-error inline" style="margin: 0 0 15px 0; padding: 10px;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('Generation Failed:', 'ats-moknah'); ?></strong>
                        <?php echo esc_html($status_details); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="ats-meta-row ats-toggle-row">
                <label class="ats-toggle">
                    <input type="checkbox" name="ats_moknah_enabled"
                           value="1" <?php checked($enabled); ?> <?php disabled(!$hasApiKey || !$hasVoices); ?>>
                    <span class="ats-toggle-slider"></span>
                    <span class="ats-toggle-label"><?php esc_html_e('Enable Text-to-Speech', 'ats-moknah'); ?></span>
                </label>
                <br>
                <label class="ats-toggle">
                    <input type="checkbox" name="ats_moknah_preprocessing"
                           value="1" <?php checked($preprocessing); ?> <?php disabled(!$hasApiKey || !$hasVoices); ?>>
                    <span class="ats-toggle-slider"></span>
                    <span class="ats-toggle-label"><?php esc_html_e('Enable AI Preprocessing', 'ats-moknah'); ?></span>
                </label>
            </div>

            <div class="ats-meta-row">
                <label class="ats-label">
                    <span class="ats-label-text"><?php esc_html_e('Voice', 'ats-moknah'); ?></span>
                    <select name="ats_moknah_voice_id" id="ats-voice-select"
                            class="ats-select" <?php disabled(!$hasVoices); ?>>
                        <?php if ($hasVoices): ?>
                            <option value="" disabled selected>
                                <?php esc_html_e('Select a voice', 'ats-moknah'); ?>
                            </option>
                            <?php foreach ($voices as $id => $info): ?>
                                <option value="<?php echo esc_attr($id); ?>"
                                        data-sample="<?php echo esc_attr($info['sample']); ?>"
                                    <?php selected($current, $id); ?>>
                                    <?php echo esc_html($info['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value=""><?php esc_html_e('No voices available', 'ats-moknah'); ?></option>
                        <?php endif; ?>
                    </select>
                </label>
            </div>

            <?php if ($hasVoices && isset($voices[$current])): ?>
                <div class="ats-meta-row" id="ats-voice-sample">
                    <label class="ats-label">
                        <span class="ats-label-text"><?php esc_html_e('Voice Sample', 'ats-moknah'); ?></span>
                        <div class="ats-audio-wrapper">
                            <audio id="ats-sample-audio" controls
                                   src="<?php echo esc_url($voices[$current]['sample'] ?? ''); ?>"></audio>
                        </div>
                    </label>
                </div>
            <?php endif; ?>

            <?php if ($audioUrl): ?>
                <div class="ats-meta-row">
                    <label class="ats-label">
                        <span class="ats-label-text"><?php esc_html_e('Generated Audio', 'ats-moknah'); ?></span>
                        <div class="ats-audio-wrapper">
                            <audio controls src="<?php echo esc_url($audioUrl); ?>"></audio>
                        </div>
                    </label>
                </div>
            <?php endif; ?>

            <?php if ($status): ?>
                <div class="ats-meta-row">
                    <span class="ats-label-text"><?php esc_html_e('Status', 'ats-moknah'); ?></span>
                    <div class="ats-status-badge ats-status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $status))); ?>">
                        <span class="dashicons dashicons-<?php
                        echo $status === 'failed' ? 'warning' : ($audioUrl ? 'yes-alt' : 'clock');
                        ?>"></span>
                        <?php echo esc_html(ucfirst($status)); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ats-meta-row">
                <button type="button"
                        class="button components-button is-primary is-compact ats-generate-btn"
                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                    <?php disabled(!$hasApiKey || !$hasVoices); ?>>
                    <?php echo esc_html($buttonLabel); ?>
                </button>
            </div>
        </div>

        <?php
    }

    public static function savePostMeta($post_id, $post)
    {
        if ($post->post_type !== 'post') return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['ats_moknah_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ats_moknah_nonce'])), 'ats_moknah_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_ats_moknah_enabled', isset($_POST['ats_moknah_enabled']) ? '1' : '0');

        $voice = isset($_POST['ats_moknah_voice_id']) ? sanitize_text_field(wp_unslash($_POST['ats_moknah_voice_id'])) : '';
        if ($voice && array_key_exists($voice, self::getVoices())) {
            update_post_meta($post_id, '_ats_moknah_voice_id', $voice);
        } else {
            delete_post_meta($post_id, '_ats_moknah_voice_id');
        }
    }

    public static function settingsPage()
    {
        $voices = self::getVoices();
        $hasVoices = !empty($voices);

        if (
            isset($_GET['settings-updated'], $_GET['_wpnonce']) &&
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_GET['_wpnonce'])),
                'ats_moknah_settings_updated'
            ) &&
            'true' === sanitize_text_field(wp_unslash($_GET['settings-updated']))
        ) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Settings saved successfully.', 'ats-moknah') .
                '</p></div>';
        }


        ?>
        <div class="wrap ats-settings-wrap">
            <div class="ats-settings-header">
                <h1>
                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../assets/favicon.ico'); ?>"
                         alt="<?php esc_attr_e('Moknah Logo', 'ats-moknah'); ?>" class="ats-settings-logo">
                    <?php esc_html_e('ATS Moknah Settings', 'ats-moknah'); ?>
                </h1>
                <p class="description"><?php esc_html_e('Configure your Article to Speech settings and default preferences.', 'ats-moknah'); ?></p>
            </div>

            <?php if (!$hasVoices && get_option('ats_moknah_api_key')): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Warning:', 'ats-moknah'); ?></strong>
                        <?php esc_html_e('Unable to load voices from Moknah API. Please verify your API key is correct and has the necessary permissions.', 'ats-moknah'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php" class="ats-settings-form">
                <?php settings_fields('ats_moknah_settings'); ?>

                <div class="ats-settings-container">

                    <div class="ats-settings-card">
                        <h2 class="ats-card-title">
                            <span class="dashicons dashicons-admin-network"></span>
                            <?php esc_html_e('API Configuration', 'ats-moknah'); ?>
                        </h2>

                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Moknah API Key', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Enter your Moknah API key to enable text-to-speech conversion.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <input type="password"
                                       name="ats_moknah_api_key"
                                       value="<?php echo esc_attr(get_option('ats_moknah_api_key')); ?>"
                                       class="ats-input ats-input-large"
                                       placeholder="<?php esc_attr_e('Enter your API key', 'ats-moknah'); ?>" required>
                            </div>
                        </div>
                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Article Selector', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Enter the innermost CSS selector that directly contains the post title and body.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <input type="text"
                                       name="ats_moknah_article_selector"
                                       value="<?php echo esc_attr(get_option('ats_moknah_article_selector')); ?>"
                                       class="ats-input ats-input-large"
                                       placeholder="exp: .wp-site-blocks" required>
                            </div>
                        </div>
                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Skipped Selectors', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('A list of CSS selectors inside "Article Selector" which you plan to ignore them from being highlighted', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-note"><?php esc_html_e('\'.highlighter-skip\' Selector is included by default', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <input type="text"
                                       name="ats_moknah_skipped_selectors"
                                       value="<?php echo esc_attr(get_option('ats_moknah_skipped_selectors')); ?>"
                                       class="ats-input ats-input-large"
                                       placeholder="exp: [.skip, .wp-caption-text]">
                            </div>
                        </div>
                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Callback url', 'ats-moknah'); ?> <span
                                            class="ats-settings-label-tag"><?php esc_html_e('( optional )', 'ats-moknah'); ?></span></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Enter the callback URL to receive audio file notifications.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <input type="url"
                                       name="ats_moknah_callback_url"
                                       value="<?php echo esc_attr(get_option('ats_moknah_callback_url')); ?>"
                                       class="ats-input ats-input-large"
                                    <?php
                                    /* translators: %s: Default callback URL */
                                    printf(
                                        'placeholder="%s"',
                                        esc_attr(
                                            sprintf(
                                                __('Default: %s', 'ats-moknah'),
                                                rest_url('ats-moknah/v1/callback')
                                            )
                                        )
                                    );
                                    ?>
                                >
                            </div>
                        </div>
                    </div>

                    <div class="ats-settings-card">
                        <h2 class="ats-card-title">
                            <span class="dashicons dashicons-microphone"></span>
                            <?php esc_html_e('Voice Settings', 'ats-moknah'); ?>
                        </h2>

                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Default Voice', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Select the default voice for new articles.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <?php if ($hasVoices): ?>
                                    <select name="ats_moknah_voice_id" id="ats-settings-voice-select"
                                            class="ats-select">
                                        <option value="" disabled selected>
                                            <?php esc_html_e('Select a voice', 'ats-moknah'); ?>
                                        </option>
                                        <?php
                                        $defaultVoice = get_option('ats_moknah_voice_id');
                                        foreach ($voices as $id => $info):
                                            ?>
                                            <option value="<?php echo esc_attr($id); ?>"
                                                    data-sample="<?php echo esc_attr($info['sample']); ?>"
                                                <?php selected($defaultVoice, $id); ?>>
                                                <?php echo esc_html($info['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if ($defaultVoice && isset($voices[$defaultVoice]['sample'])): ?>
                                        <div class="ats-audio-wrapper" style="margin-top: 12px;">
                                            <audio id="ats-settings-sample-audio" controls
                                                   src="<?php echo esc_url($voices[$defaultVoice]['sample']); ?>"></audio>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="description" style="color: #d63638;">
                                        <?php esc_html_e('Please save your API key first to load available voices.', 'ats-moknah'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="ats-settings-card">
                        <h2 class="ats-card-title">
                            <span class="dashicons dashicons-email"></span>
                            <?php esc_html_e('Notification Settings', 'ats-moknah'); ?>
                        </h2>

                        <div class="ats-settings-row">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Email Notifications', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Choose who receives notifications when audio generation completes.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <label class="ats-checkbox-label">
                                    <input type="checkbox"
                                           name="ats_moknah_notify_author"
                                           value="1"
                                        <?php checked(get_option('ats_moknah_notify_author', '0'), '1'); ?>>
                                    <span><?php esc_html_e('Notify post author when audio is ready', 'ats-moknah'); ?></span>
                                </label>
                                <br>
                                <label class="ats-checkbox-label" style="margin-top: 8px;">
                                    <input type="checkbox"
                                           name="ats_moknah_notify_admin"
                                           value="1"
                                        <?php checked(get_option('ats_moknah_notify_admin'), '0'); ?>>
                                    <span><?php esc_html_e('Notify site administrator', 'ats-moknah'); ?></span>
                                </label>
                                <br>
                                <label class="ats-checkbox-label" style="margin-top: 8px;">
                                    <input type="checkbox"
                                           name="ats_moknah_notify_failures"
                                           value="1"
                                        <?php checked(get_option('ats_moknah_notify_failures', '0'), '1'); ?>>
                                    <span><?php esc_html_e('Send notifications for failed generations', 'ats-moknah'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="ats-settings-row"
                             style="border-top: 1px solid #dcdcde; padding-top: 20px; margin-top: 20px;">
                            <label class="ats-settings-label">
                                <span class="ats-settings-label-text"><?php esc_html_e('Test Email Configuration', 'ats-moknah'); ?></span>
                                <span class="ats-settings-label-desc"><?php esc_html_e('Send a test email to verify your WordPress email setup is working correctly.', 'ats-moknah'); ?></span>
                            </label>
                            <div class="ats-settings-field">
                                <button type="button" id="ats-test-email-btn" class="button button-secondary">
                                    <span class="dashicons dashicons-email-alt" style="margin-top: 3px;"></span>
                                    <?php esc_html_e('Send Test Email', 'ats-moknah'); ?>
                                </button>
                                <div id="ats-test-email-result" style="margin-top: 10px; display: none;"></div>
                                <p class="description" style="margin-top: 10px;">
                                    <?php
                                    /* translators: %s: Email address the test email will be sent to */
                                    printf(
                                        esc_html__('The test email will be sent to: %s', 'ats-moknah'),
                                        '<strong>' . esc_html(wp_get_current_user()->user_email) . '</strong>'
                                    );
                                    ?>


                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="ats-settings-footer">
                    <?php submit_button(__('Save Settings', 'ats-moknah'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>

        <?php if ($hasVoices): ?>
        <script>
			jQuery(document).ready(function ($) {
				$('#ats-settings-voice-select').on('change', function () {
					const sample = $(this).find(':selected').data('sample');
					const audioEl = $('#ats-settings-sample-audio');

					if (sample) {
						if (audioEl.length) {
							audioEl.attr('src', sample);
						} else {
							$(this).parent().append(
								'<div class="ats-audio-wrapper" style="margin-top: 12px;">' +
								'<audio id="ats-settings-sample-audio" controls src="' + sample + '"></audio>' +
								'</div>'
							);
						}
					}
				});
			});
        </script>
    <?php endif; ?>
        <?php
    }

    private static function normalize_punctuation($text)
    {
        // Split by line breaks
        $lines = preg_split('/\r\n|\r|\n/', $text);

        foreach ($lines as &$line) {
            $line = trim($line);

            if ($line === '') continue;

            // If line does NOT end with punctuation
            if (!preg_match('/[.!?…؟!]$/u', $line)) {
                $line .= '.';
            }
        }

        return implode("\n", $lines);
    }

    public static function generateTTS($post_id)
    {
        if (get_post_meta($post_id, '_ats_moknah_processing', true)) {
            throw new \Exception('Audio generation is already in progress for this post.');
        }

        update_post_meta($post_id, '_ats_moknah_processing', '1');

        try {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'post') {
                throw new \Exception('Invalid post type. Only standard posts are supported.');
            }

            $enabled = get_post_meta($post_id, '_ats_moknah_enabled', true);
            if ($enabled !== '1') {
                throw new \Exception('Text-to-Speech is not enabled for this post.');
            }

            update_post_meta($post_id, '_ats_moknah_status', 'processing');
            update_post_meta($post_id, '_ats_moknah_status_details', 'Preparing content for audio generation...');

            $apiKey = get_option('ats_moknah_api_key');
            if (empty($apiKey)) {
                throw new \Exception('API key is not configured. Please add your Moknah API key in plugin settings.');
            }

            $callbackURL = get_option('ats_moknah_callback_url');
            if (empty($callbackURL)) {
                $callbackURL = rest_url('ats-moknah/v1/callback');
            }

            $voice_id = get_post_meta($post_id, '_ats_moknah_voice_id', true);
            if (!$voice_id) {
                $voice_id = get_option('ats_moknah_voice_id');
            }

            if (empty($voice_id)) {
                throw new \Exception('No voice selected. Please select a voice in post settings or configure a default voice.');
            }

            $client = new \ATS_Moknah\MoknahClient(
                'https://api.moknah.io/process-text',
                $apiKey
            );

            $content = $post->post_title . "\n\n" . $post->post_content;

            $content = strip_shortcodes($content);

            $skip_phrases = ['Generated by Moknah.io'];
            foreach ($skip_phrases as $skip) {
                $content = str_replace($skip, '', $content);
            }

            $content = preg_replace('/<\/(p|h[1-6]|li|div)>/i', "\n", $content);

            $content = wp_strip_all_tags($content);

            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

            $content = preg_replace('/[ \t]+/', ' ', $content);
            $content = preg_replace('/\n+/', "\n", $content);
            $content = trim($content);

            $content = self::normalize_punctuation($content);

            if (empty($content)) {
                throw new \Exception('Post content is empty after processing. Please add content to your post.');
            }

            if (strlen($content) < 50) {
                throw new \Exception('Post content is too short (less than 50 characters). Please add more content.');
            }


            $preprocessing = get_post_meta($post_id, '_ats_moknah_preprocessing', true);

            $client->processText(
                $content,
                $post->post_title,
                (string)$post_id,
                $voice_id,
                $callbackURL,
                $preprocessing,
                true
            );

            update_post_meta($post_id, '_ats_moknah_status', 'queued');
            update_post_meta($post_id, '_ats_moknah_status_details', 'Audio generation request sent successfully. Processing may take a few minutes.');


        } catch (\Exception $e) {
            $raw_message = $e->getMessage();

            if (strpos($raw_message, 'API key') !== false || strpos($raw_message, 'Unauthorized') !== false) {
                $error_message = __('API authentication failed. Please check your API key in settings.', 'ats-moknah');
            } elseif (strpos($raw_message, 'cURL') !== false || strpos($raw_message, 'Connection') !== false) {
                $error_message = __('Unable to connect to Moknah API. Please check your internet connection and try again.', 'ats-moknah');
            } elseif (strpos($raw_message, 'timeout') !== false) {
                $error_message = __('Request timed out. The Moknah API may be experiencing issues. Please try again later.', 'ats-moknah');
            } else {
                // Fallback – generic safe message
                $error_message = __('An unexpected error occurred while processing the request.', 'ats-moknah');
            }

            update_post_meta($post_id, '_ats_moknah_status', 'failed');
            update_post_meta($post_id, '_ats_moknah_status_details', $error_message);
            delete_post_meta($post_id, '_ats_moknah_processing');

            // Re-throw a sanitized, user-safe message
            throw new \Exception($error_message);

        } finally {
            delete_post_meta($post_id, '_ats_moknah_processing');
        }

    }
}

function voice_list()
{
    try {
        $url = "https://moknah.io/api/v1/voices/";
        $apiKey = get_option('ats_moknah_api_key');

        if (empty($apiKey)) {
            return ['error' => 'API key is not configured'];
        }

        // Use WordPress HTTP API instead of cURL
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept-Language' => 'en'
            ],
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return ['error' => 'Connection error: Unable to reach Moknah API. Please check your internet connection.'];
        }

        $httpCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($httpCode === 401 || $httpCode === 403) {
            return ['error' => 'Authentication failed. Please check your API key in settings.'];
        }

        if ($httpCode !== 200) {
            return ['error' => "API request failed (HTTP $httpCode). Please try again later."];
        }

        $voices = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid response from Moknah API. Please try again later.'];
        }

        if (!is_array($voices)) {
            return ['error' => 'Unexpected response format from Moknah API.'];
        }

        if (empty($voices)) {
            return ['error' => 'No voices available. Please contact Moknah support.'];
        }

        $voicelist = [];

        foreach ($voices as $voice) {
            if (!is_array($voice)) {
                continue;
            }

            if (!isset($voice['voice_id'], $voice['voice_name'], $voice['voice_sample_url'])) {
                continue;
            }

            $voicelist[$voice['voice_id']] = [
                "label" => $voice['voice_name'],
                "sample" => $voice['voice_sample_url'],
            ];
        }

        if (empty($voicelist)) {
            return ['error' => 'No valid voices found in API response.'];
        }

        return $voicelist;

    } catch (\Exception $e) {
        return ['error' => 'Unexpected error loading voices: ' . $e->getMessage()];
    }
}