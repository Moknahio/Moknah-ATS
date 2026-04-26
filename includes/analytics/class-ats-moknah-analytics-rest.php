<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AtsMoknahAnalyticsRest {

    public static function register(): void {
        add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
    }

    public static function register_routes(): void {
        register_rest_route(
            'ats-moknah/v1',
            '/analytics',
            array(
                'methods'             => 'POST',
                'permission_callback' => '__return_true', // Public endpoint by design.
                'args'                => array(
                    'post_id'        => array(
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                    'event'          => array(
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ),
                    'listen_seconds' => array(
                        'type'              => 'number',
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                ),
                'callback'            => array( self::class, 'handle_event' ),
            )
        );
    }

    private static function check_rate_limit(): bool {
        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $key = 'ats_moknah_rl_' . md5( $ip );

        $count = (int) wp_cache_get( $key, 'ats_moknah' );
        $count++;

        wp_cache_set( $key, $count, 'ats_moknah', AtsMoknahAnalyticsDb::RATE_LIMIT_WINDOW );

        return $count <= AtsMoknahAnalyticsDb::RATE_LIMIT_MAX_HITS;
    }

    private static function is_valid_event( string $event ): bool {
        $allowed = array( 'impression', 'play', 'complete', 'listen' );
        return in_array( $event, $allowed, true );
    }

    private static function normalize_event( string $event ): string {
        if ( 'heartbeat' === $event ) {
            return 'listen';
        }

        return $event;
    }

    private static function is_trackable_post( int $post_id ): bool {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        // Only track published posts on public post type.
        if ( 'publish' !== get_post_status( $post ) ) {
            return false;
        }

        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
            return false;
        }

        return true;
    }

    private static function persist_event( int $post_id, string $event, int $seconds ): bool {
        $event = self::normalize_event( sanitize_key( $event ) );
        error_log("Persisting event: post_id={$post_id}, event={$event}, seconds={$seconds}" );
        $seconds = max( 0, min( (int) $seconds, AtsMoknahAnalyticsDb::LISTEN_CAP ) );

        if ( ! $post_id || ! self::is_trackable_post( $post_id ) || ! self::is_valid_event( $event ) ) {
            return false;
        }

        global $wpdb;
        AtsMoknahAnalyticsDb::maybe_create_tables();

        $totals_table = AtsMoknahAnalyticsDb::qt( $wpdb, AtsMoknahAnalyticsDb::TABLE_TOTALS );
        $daily_table  = AtsMoknahAnalyticsDb::qt( $wpdb, AtsMoknahAnalyticsDb::TABLE_DAILY );

        $now = current_time( 'mysql' );
        $tz  = wp_timezone();
        $day = ( new \DateTime( 'now', $tz ) )->format( 'Y-m-d' );

        // Ensure base rows exist before incrementing metrics.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$totals_table} (post_id, impressions, plays, completions, listen_seconds, updated_at)
				 VALUES (%d,0,0,0,0,%s)
				 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                $post_id,
                $now
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$daily_table} (post_id, day, impressions, plays, completions, listen_seconds, updated_at)
				 VALUES (%d,%s,0,0,0,0,%s)
				 ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)",
                $post_id,
                $day,
                $now
            )
        );

        $inc_impressions   = ( 'impression' === $event ) ? 1 : 0;
        $inc_plays         = ( 'play' === $event ) ? 1 : 0;
        $inc_completions   = ( 'complete' === $event ) ? 1 : 0;
        $inc_listen_second = ( 'listen' === $event ) ? $seconds : 0;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$totals_table}
				 SET impressions = impressions + %d,
				     plays = plays + %d,
				     completions = completions + %d,
				     listen_seconds = listen_seconds + %d,
				     updated_at = %s
				 WHERE post_id = %d",
                $inc_impressions,
                $inc_plays,
                $inc_completions,
                $inc_listen_second,
                $now,
                $post_id
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$daily_table}
				 SET impressions = impressions + %d,
				     plays = plays + %d,
				     completions = completions + %d,
				     listen_seconds = listen_seconds + %d,
				     updated_at = %s
				 WHERE post_id = %d AND day = %s",
                $inc_impressions,
                $inc_plays,
                $inc_completions,
                $inc_listen_second,
                $now,
                $post_id,
                $day
            )
        );

        return true;
    }

    public static function track_event( int $post_id, string $event, int $listen_seconds = 0 ): bool {
        return self::persist_event( $post_id, $event, $listen_seconds );
    }

    public static function handle_event( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! self::check_rate_limit() ) {
            return new \WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
        }

        $post_id = (int) $request->get_param( 'post_id' );
        $event   = self::normalize_event( sanitize_key( (string) $request->get_param( 'event' ) ) );
        $seconds = (int) $request->get_param( 'listen_seconds' );
        $seconds = max( 0, min( $seconds, AtsMoknahAnalyticsDb::LISTEN_CAP ) );

        if ( ! $post_id || ! self::is_trackable_post( $post_id ) ) {
            return new \WP_REST_Response( array( 'error' => 'invalid_post' ), 400 );
        }

        if ( ! self::is_valid_event( $event ) ) {
            return new \WP_REST_Response( array( 'error' => 'invalid_event' ), 400 );
        }

        if ( ! self::persist_event( $post_id, $event, $seconds ) ) {
            return new \WP_REST_Response( array( 'error' => 'persist_failed' ), 500 );
        }

        return new \WP_REST_Response(
            array(
                'ok'    => true,
                'event' => $event,
            ),
            200
        );
    }
}