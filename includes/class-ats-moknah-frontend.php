<?php
namespace ATS_Moknah;

if (!defined('ABSPATH')) exit;

class Frontend {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('the_content', [__CLASS__, 'add_audio_player']);
    }

    public static function enqueue_assets() {
        if (!is_single()) return;

        global $post;
        if (!$post) return;

        $enabled = get_post_meta($post->ID, '_ats_moknah_enabled', true);
        if ($enabled !== '1') return;

        wp_enqueue_style(
            'ats-mini-icons',
            plugin_dir_url(__FILE__) . '../assets/fontawesome/style.css',
            [],
            '1.0'
        );

        // Plugin CSS
        wp_enqueue_style(
            'mk-mp-d1',
            plugin_dir_url(__FILE__) . '../assets/mk-mp-d1.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/mk-mp-d1.css')
        );

        // Scripts
        wp_enqueue_script(
            'moknah-highlighter',
            plugin_dir_url(__FILE__) .'../assets/moknah-highlighter.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../assets/moknah-highlighter.js'),
            true
        );

        wp_enqueue_script(
            'mk-mp-d1',
            plugin_dir_url(__FILE__) . '../assets/mk-mp-d1.js',
            ['jquery'],
            filemtime(plugin_dir_path(__FILE__) . '../assets/mk-mp-d1.js'),
            true
        );
        $srt_url = get_post_meta($post->ID, '_ats_moknah_srt_url', true);
        $srt_js = $srt_url ? "'" . esc_js($srt_url) . "'" : 'null';
        $articleSelector = get_option('ats_moknah_article_selector');
        $skippedSelectors = get_option('ats_moknah_skipped_selectors', []);
        try {
            // Remove leading dot from each selector
            if (!is_array($skippedSelectors)) {
                $skippedSelectors = explode(',', $skippedSelectors);
            }
            $skippedSelectors = array_map(function ($selector) {
                return ltrim($selector, '.');
            }, $skippedSelectors);

            // Append 'highlighter-skip'

            $skippedSelectors[] = 'highlighter-skip';
        } catch (\Exception $e) {
            $skippedSelectors = ['highlighter-skip'];
        }
        $skippedSelectors = json_encode($skippedSelectors);
        $inline_js = "window.MoknahTTS.init({
            srtSrc: {$srt_js},
            contentSelector: '{$articleSelector}',
            audioID: 'mk-mp-d1-audio',
            skipClasses: $skippedSelectors,
            debug: true,
            styles: {
                baseColor: '#333',
                highlightColor: '#f6a21f',
                highlightTextColor: '#000',
                underlineHeight: '3px',
                underlineOffset: '-2px',
                animationDuration: '0.3s'
            }
        });";

        wp_add_inline_script('mk-mp-d1', $inline_js);

    }

    public static function add_audio_player($content) {
        if (!is_single()) return $content;
        global $post;
        if (!$post) return $content;

        $enabled = get_post_meta($post->ID, '_ats_moknah_enabled', true);
        if ($enabled !== '1') return $content;

        $post_id = get_the_ID();

        // Get dynamic audio URL from post meta (or generate from post ID)
        $audio_url = get_post_meta($post_id, '_ats_moknah_audio_url', true);
        if (empty($audio_url)) {
            // fallback audio URL if nothing is set
            $audio_url = 'https://storage.moknah.io/default-audio.mp3';
        }

        // Load template HTML
        $template = file_get_contents(plugin_dir_path(__FILE__) . '../mk-mp-player-template.html');
        // Replace placeholder with dynamic URL
        $player_html = str_replace('{{audio_url}}', esc_url($audio_url), $template);

        // Insert after featured image
        if (preg_match('/(<span class="byline">.*?<\/span>)/i', $content, $matches)) {
            $content = str_replace($matches[1], $matches[1] . "\n<div class='mk-mp-player-wrapper'>\n{$player_html}\n</div>\n", $content);
        } else {
            // fallback: prepend at top
            $content = "<div class='mk-mp-player-wrapper'>\n{$player_html}\n</div>\n" . $content;
        }
        return $content;

    }
}
