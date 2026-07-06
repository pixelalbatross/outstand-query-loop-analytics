<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Handles the OAuth 2.0 authorization flow with Google via admin-post.php.
 */
class Auth extends BaseModule {

	/**
	 * Admin-post action name for the OAuth callback.
	 *
	 * @var string
	 */
	public const CALLBACK_ACTION = 'outstand_query_loop_analytics_oauth_callback';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::CALLBACK_ACTION, [ $this, 'handle_oauth_callback' ] );
		add_action( 'admin_post_nopriv_' . self::CALLBACK_ACTION, [ $this, 'handle_oauth_callback_nopriv' ] );
	}

	/**
	 * Get the full redirect URI for Google OAuth.
	 *
	 * @return string
	 */
	public static function get_redirect_uri(): string {
		$url = admin_url( 'admin-post.php?action=' . self::CALLBACK_ACTION );

		/**
		 * Filters the OAuth redirect URI.
		 *
		 * Useful for local development where https is unavailable.
		 *
		 * @param string $url The redirect URI.
		 */
		return (string) apply_filters( 'outstand_query_loop_analytics_redirect_uri', $url );
	}

	/**
	 * Reject unauthenticated callback hits early.
	 */
	public function handle_oauth_callback_nopriv(): void {
		wp_die( esc_html__( 'You must be logged in to complete authorization.', 'outstand-query-loop-analytics' ) );
	}

	/**
	 * Handle the OAuth callback from Google.
	 */
	public function handle_oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'outstand-query-loop-analytics' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( ! $state ) {
			wp_die( esc_html__( 'Missing OAuth state.', 'outstand-query-loop-analytics' ) );
		}

		$nonce_check = wp_verify_nonce( $state, 'outstand_query_loop_analytics_oauth' );
		if ( ! $nonce_check ) {
			Logger::error( 'oauth_state_invalid_or_expired' );
			$this->redirect_to_settings( [ 'error' => 'oauth_expired' ] );
		}

		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			Logger::error( 'oauth_denied: ' . $error_code );
			$this->redirect_to_settings( [ 'error' => 'oauth_denied' ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( empty( $code ) ) {
			wp_die( esc_html__( 'No authorization code received.', 'outstand-query-loop-analytics' ) );
		}

		$settings = Settings::get_settings();
		$client   = new GoogleClient(
			$settings['client_id'],
			$settings['client_secret'],
			self::get_redirect_uri()
		);

		$token = $client->fetch_access_token( $code );

		if ( is_wp_error( $token ) ) {
			Logger::error( 'oauth_exchange_failed: ' . $token->get_error_message() );
			$this->redirect_to_settings( [ 'error' => 'oauth_failed' ] );
		}

		Settings::set_tokens( $token );
		delete_transient( Analytics::ERROR_BACKOFF_KEY );

		// Schedule initial fetch soon after connection.
		if ( ! wp_next_scheduled( Analytics::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 10, Analytics::CRON_HOOK );
		}

		$this->redirect_to_settings( [ 'updated' => 'connected' ] );
	}

	/**
	 * Redirect back to settings with context.
	 *
	 * @param array<string, string> $args Query args.
	 */
	private function redirect_to_settings( array $args ): void {
		$args['page'] = Settings::PAGE_SLUG;

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}
}
