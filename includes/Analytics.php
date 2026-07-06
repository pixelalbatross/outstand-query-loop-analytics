<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Fetches popular posts data from GA4 via WP-Cron
 * and caches results in an option with error backoff.
 */
class Analytics extends BaseModule {

	/**
	 * Option key holding the popular posts data.
	 *
	 * @var string
	 */
	public const CACHE_KEY = 'outstand_query_loop_analytics_popular_posts';

	/**
	 * Transient key for error backoff.
	 *
	 * @var string
	 */
	public const ERROR_BACKOFF_KEY = 'outstand_query_loop_analytics_error_backoff';

	/**
	 * Transient key for last-sync timestamp.
	 *
	 * @var string
	 */
	public const LAST_SYNC_KEY = 'outstand_query_loop_analytics_last_sync';

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'outstand_query_loop_analytics_fetch';

	/**
	 * Token-refresh lock transient key.
	 *
	 * @var string
	 */
	public const REFRESH_LOCK_KEY = 'outstand_query_loop_analytics_refresh_lock';

	/**
	 * Custom cron schedule name.
	 *
	 * @var string
	 */
	public const CRON_SCHEDULE = 'outstand_query_loop_analytics_interval';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interval derives from the cache_duration setting.
		add_filter( 'cron_schedules', [ $this, 'register_cron_schedule' ] );
		add_action( self::CRON_HOOK, [ $this, 'fetch_data' ] );
		add_action( 'admin_init', [ $this, 'maybe_schedule_cron' ] );
		add_action( 'update_option_' . Settings::OPTION_SETTINGS, [ $this, 'reschedule_on_settings_change' ], 10, 2 );
	}

	/**
	 * Re-schedule cron when cache_duration, credentials, or property_id change.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public function reschedule_on_settings_change( $old_value, $new_value ): void {
		$old = is_array( $old_value ) ? $old_value : [];
		$new = is_array( $new_value ) ? $new_value : [];

		// client_secret is re-encrypted with a fresh nonce on every save; compare the tag-stripped
		// ciphertext is meaningless, so fall back to a presence check (empty vs non-empty).
		$old_has_secret = ! empty( $old['client_secret'] );
		$new_has_secret = ! empty( $new['client_secret'] );

		$watched = [ 'cache_duration', 'client_id', 'property_id' ];
		$changed = $old_has_secret !== $new_has_secret;
		foreach ( $watched as $key ) {
			if ( $changed ) {
				break;
			}
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return;
		}

		self::unschedule();
		$this->maybe_schedule_cron();
	}

	/**
	 * Register the custom cron schedule based on cache_duration setting.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Existing schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function register_cron_schedule( array $schedules ): array {
		$settings = Settings::get_settings();
		$hours    = max( 1, absint( $settings['cache_duration'] ?? 12 ) );

		$schedules[ self::CRON_SCHEDULE ] = [
			'interval' => $hours * HOUR_IN_SECONDS,
			'display'  => __( 'Outstand popular posts interval', 'outstand-query-loop-analytics' ),
		];

		return $schedules;
	}

	/**
	 * Ensure the fetch cron event is scheduled.
	 */
	public function maybe_schedule_cron(): void {
		if ( ! Settings::is_configured() ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Fetch popular posts data from GA4 and store in option.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function fetch_data(): bool {
		if ( ! Settings::is_configured() ) {
			return false;
		}

		// Skip while backoff is active.
		if ( false !== get_transient( self::ERROR_BACKOFF_KEY ) ) {
			return false;
		}

		$tokens = Settings::get_tokens();
		if ( ! $tokens ) {
			return false;
		}

		$settings = Settings::get_settings();

		$client = new GoogleClient(
			$settings['client_id'],
			$settings['client_secret'],
			Auth::get_redirect_uri()
		);
		$client->set_access_token( $tokens );

		if ( $client->is_token_expired() ) {
			$tokens = $this->refresh_with_lock( $client, $tokens );
			if ( is_wp_error( $tokens ) ) {
				$this->trigger_backoff( 'token_refresh_failed', $tokens->get_error_message() );
				return false;
			}
		}

		$date_range_days = absint( $settings['date_range_days'] ?? 30 );
		$fetch_limit     = absint( $settings['fetch_limit'] ?? 20 );

		/**
		 * Filters the date range for the GA4 popular posts report.
		 *
		 * @param array{start: string, end: string} $date_range Start and end dates.
		 */
		$date_range = apply_filters(
			'outstand_query_loop_analytics_date_range',
			[
				'start' => gmdate( 'Y-m-d', strtotime( '-' . $date_range_days . ' days' ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
			]
		);

		/**
		 * Filters the maximum number of pages to fetch from GA4.
		 *
		 * @param int $limit The fetch limit.
		 */
		$fetch_limit = (int) apply_filters( 'outstand_query_loop_analytics_fetch_limit', $fetch_limit );

		$pages = $client->get_popular_pages(
			$settings['property_id'],
			$date_range['start'],
			$date_range['end'],
			$fetch_limit
		);

		if ( is_wp_error( $pages ) ) {
			$this->trigger_backoff( 'ga4_fetch_failed', $pages->get_error_message() );
			return false;
		}

		/**
		 * Filters the post types to resolve from GA4 page paths.
		 *
		 * @param array<string> $post_types Post types to include.
		 */
		$post_types = apply_filters( 'outstand_query_loop_analytics_post_types', [ 'post' ] );

		$popular_posts = [];
		$resolved      = [];

		foreach ( $pages as $page ) {
			$post_id = $this->resolve_path_to_post_id( $page['path'] );
			if ( ! $post_id || isset( $resolved[ $post_id ] ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}

			if ( $post->post_status !== 'publish' ) {
				continue;
			}

			$resolved[ $post_id ] = true;
			$popular_posts[]      = [
				'post_id'   => $post_id,
				'pageviews' => (int) $page['pageviews'],
			];
		}

		/**
		 * Filters the final popular posts data before caching.
		 *
		 * @param array<array{post_id: int, pageviews: int}> $popular_posts The popular posts data.
		 */
		$popular_posts = apply_filters( 'outstand_query_loop_analytics_popular_posts_data', $popular_posts );

		update_option( self::CACHE_KEY, $popular_posts, false );
		set_transient( self::LAST_SYNC_KEY, time(), WEEK_IN_SECONDS );

		return true;
	}

	/**
	 * Resolve a GA4 pagePath to a post ID.
	 *
	 * @param string $path Raw pagePath from GA.
	 * @return int Post ID or 0 on failure.
	 */
	private function resolve_path_to_post_id( string $path ): int {
		$path = trim( $path );
		if ( $path === '' ) {
			return 0;
		}

		// Strip host if a full URL slipped through.
		$parts = wp_parse_url( $path );
		if ( isset( $parts['host'] ) ) {
			$path = $parts['path'] ?? '/';
		} else {
			$path = strtok( $path, '?#' );
		}

		if ( ! is_string( $path ) || $path === '' ) {
			return 0;
		}

		if ( $path[0] !== '/' ) {
			$path = '/' . $path;
		}

		$url = home_url( $path );

		if ( function_exists( 'wpcom_vip_url_to_postid' ) ) {
			return (int) wpcom_vip_url_to_postid( $url );
		}

		return $this->cached_url_to_postid( $url );
	}

	/**
	 * Cached fallback for url_to_postid() mirroring wpcom_vip_url_to_postid().
	 *
	 * @param string $url Absolute URL.
	 * @return int Post ID or 0.
	 */
	private function cached_url_to_postid( string $url ): int {
		$cache_key = md5( $url );
		$cached    = wp_cache_get( $cache_key, 'url_to_postid' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$post_id = (int) url_to_postid( $url );
		wp_cache_set( $cache_key, $post_id, 'url_to_postid', 3 * HOUR_IN_SECONDS );

		return $post_id;
	}

	/**
	 * Refresh tokens with a short-lived lock to avoid parallel refreshes.
	 *
	 * @param GoogleClient         $client Google client.
	 * @param array<string, mixed> $tokens Current tokens.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function refresh_with_lock( GoogleClient $client, array $tokens ): array|\WP_Error {
		if ( empty( $tokens['refresh_token'] ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'Missing refresh token.', 'outstand-query-loop-analytics' ) );
		}

		// Atomic lock acquisition via add_option (INSERT ... ON DUPLICATE KEY fails).
		$acquired = add_option( self::REFRESH_LOCK_KEY, time(), '', false );
		if ( ! $acquired ) {
			$existing = get_option( self::REFRESH_LOCK_KEY );
			$age      = is_numeric( $existing ) ? ( time() - (int) $existing ) : PHP_INT_MAX;

			if ( $age < 30 ) {
				// Another process holds a fresh lock; reuse stored tokens if valid.
				$stored = Settings::get_tokens();
				if ( $stored && ! empty( $stored['access_token'] ) ) {
					$client->set_access_token( $stored );
					if ( ! $client->is_token_expired() ) {
						return $stored;
					}
				}
				return new \WP_Error( 'refresh_in_progress', __( 'Another token refresh is in progress.', 'outstand-query-loop-analytics' ) );
			}

			// Lock stale; delete + re-acquire atomically. If someone else wins the race, bail.
			delete_option( self::REFRESH_LOCK_KEY );
			if ( ! add_option( self::REFRESH_LOCK_KEY, time(), '', false ) ) {
				return new \WP_Error( 'refresh_in_progress', __( 'Another token refresh is in progress.', 'outstand-query-loop-analytics' ) );
			}
		}

		try {
			$new_tokens = $client->refresh_token( $tokens['refresh_token'] );
			if ( is_wp_error( $new_tokens ) ) {
				return $new_tokens;
			}
			Settings::set_tokens( $new_tokens );
			$client->set_access_token( $new_tokens );
			return $new_tokens;
		} finally {
			delete_option( self::REFRESH_LOCK_KEY );
		}
	}

	/**
	 * Set an error backoff transient.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	private function trigger_backoff( string $code, string $message ): void {
		/**
		 * Filters the error backoff duration.
		 *
		 * @param int $duration Duration in seconds.
		 */
		$duration = (int) apply_filters( 'outstand_query_loop_analytics_error_backoff_duration', 15 * MINUTE_IN_SECONDS );
		set_transient( self::ERROR_BACKOFF_KEY, $code, $duration );
		Logger::error( $code . ': ' . $message );
	}

	/**
	 * Clear scheduled cron event.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
