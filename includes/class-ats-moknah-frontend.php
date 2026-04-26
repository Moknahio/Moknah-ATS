<?php

if (!defined('ABSPATH')) exit;

class AtsMoknahFrontend {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('the_content', [__CLASS__, 'add_audio_player']);
        add_action('init', function () {
            if (function_exists('pll_register_string')) {
                pll_register_string(
                    'ats_listen_label',
                    'Listen to the article',
                    'ATS Moknah'
                );
            }
        });
    }

    public static function enqueue_assets() {
        if (!is_singular('post')) return;

        global $post;
        if (!$post) return;

        $enabled = get_post_meta($post->ID, '_ats_moknah_enabled', true);
        if ($enabled !== '1') return;

        $audio_url = get_post_meta($post->ID, '_ats_moknah_audio_url', true);
        if (empty($audio_url)) return;

        wp_enqueue_style(
            'ats-mini-icons',
            plugin_dir_url(__FILE__) . '../assets/fontawesome/style.css',
            [],
            '1.0'
        );

        // Plugin CSS
        wp_enqueue_style(
            'mk-mp-d1',
            plugin_dir_url(__FILE__) . '../assets/css/mk-mp-d1.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/mk-mp-d1.css')
        );

        // Scripts
        wp_enqueue_script(
            'moknah-highlighter',
            plugin_dir_url(__FILE__) . '../assets/js/moknah-highlighter.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/moknah-highlighter.js'),
            true
        );

        wp_enqueue_script(
            'mk-mp-d1',
            plugin_dir_url(__FILE__) . '../assets/js/mk-mp-d1.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/mk-mp-d1.js'),
            true
        );
        $srt_url = get_post_meta($post->ID, '_ats_moknah_srt_url', true);
        $article_selector = (string) get_option('ats_moknah_article_selector', 'article');
        $skipped_selectors = get_option('ats_moknah_skipped_selectors', []);
        try {
            if (!is_array($skipped_selectors)) {
                $skipped_selectors = explode(',', (string) $skipped_selectors);
            }
            $skipped_selectors = array_map(function ($selector) {
                return sanitize_html_class(ltrim(trim((string) $selector), '.'));
            }, $skipped_selectors);
            $skipped_selectors = array_values(array_filter($skipped_selectors));
            $skipped_selectors[] = 'highlighter-skip';
            $skipped_selectors = array_values(array_unique($skipped_selectors));
        } catch (\Exception $e) {
            $skipped_selectors = ['highlighter-skip'];
        }
        $inline_config = [
            'srtSrc' => !empty($srt_url) ? esc_url_raw($srt_url) : null,
            'contentSelector' => sanitize_text_field($article_selector),
            'audioID' => 'mk-mp-d1-audio',
            'skipClasses' => $skipped_selectors,
            'debug' => true,
            'styles' => [
                'baseColor' => '#333',
                'highlightColor' => '#f6a21f',
                'highlightTextColor' => '#000',
                'underlineHeight' => '3px',
                'underlineOffset' => '-2px',
                'animationDuration' => '0.3s',
            ],
        ];
        $inline_js = 'window.MoknahTTS.init(' . wp_json_encode($inline_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');';

        wp_add_inline_script('mk-mp-d1', $inline_js);
        // Analytics tracker
        wp_enqueue_script(
            'ats-moknah-player-analytics',
            plugin_dir_url(__FILE__) . '../assets/js/player-analytics.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/player-analytics.js'),
            true
        );

        wp_localize_script('ats-moknah-player-analytics', 'atsmoknahAnalytics', [
            'restUrl' => esc_url_raw(rest_url('ats-moknah/v1/analytics')),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => (int)$post->ID,
            'audioSelector' => '#mk-mp-d1-audio',
            'playButtonSelector' => '#mk-mp-d1-play-pause',
        ]);

    }

    public static function add_audio_player($content) {
        if (is_admin() || !is_singular('post') || !is_main_query()) return $content;
        global $post;
        if (!$post) return $content;

        $enabled = get_post_meta($post->ID, '_ats_moknah_enabled', true);
        if ($enabled !== '1') return $content;

        $audio_url = get_post_meta($post->ID, '_ats_moknah_audio_url', true);
        if (empty($audio_url)) return $content;

        $post_id = get_the_ID();

        // Get dynamic audio URL from post meta (or generate from post ID)
        $audio_url = get_post_meta($post_id, '_ats_moknah_audio_url', true);

        $template_path = plugin_dir_path(__FILE__) . '../templates/mk-mp-player-template.html';
        $template = file_exists($template_path) ? (string) file_get_contents($template_path) : '';
        if ($template === '') return $content;

        $player_html = str_replace('{{audio_url}}', esc_url($audio_url), $template);
        $player_html = str_replace('{{listen_to_article}}', esc_html__('Listen to the article', 'ats-moknah-article-to-speech'), $player_html);
        $player_html = wp_kses($player_html, self::allowed_player_html());

        // Insert after featured image
        if (preg_match('/(<span class="byline">.*?<\/span>)/i', $content, $matches)) {
            $content = str_replace($matches[1], $matches[1] . "\n<div class='mk-mp-player-wrapper'>\n{$player_html}\n</div>\n", $content);
        } else {
            $content = "<div class='mk-mp-player-wrapper'>\n{$player_html}\n</div>\n" . $content;
        }
        return $content;

    }

    private static function allowed_player_html(): array {
        return [
            'div' => ['class' => true, 'id' => true, 'style' => true],
            'button' => ['class' => true, 'id' => true, 'type' => true],
            'i' => ['class' => true],
            'input' => ['type' => true, 'class' => true, 'id' => true, 'value' => true, 'step' => true, 'min' => true, 'max' => true],
            'span' => ['id' => true, 'class' => true],
            'audio' => ['id' => true, 'src' => true, 'controls' => true],
            'a' => ['href' => true, 'target' => true, 'rel' => true],
        ];
    }
}
