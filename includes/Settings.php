<?php

namespace Outstand\WP\QueryLoop\Analytics;

/**
 * Admin settings page for Google Analytics integration.
 */
class Settings extends BaseModule {

	/**
	 * The settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'outstand-query-loop-analytics';

	/**
	 * Option name for plugin settings.
	 *
	 * @var string
	 */
	public const OPTION_SETTINGS = 'outstand_query_loop_analytics_settings';

	/**
	 * Option name for encrypted OAuth tokens.
	 *
	 * @var string
	 */
	public const OPTION_TOKENS = 'outstand_query_loop_analytics_tokens';

	/**
	 * Transient key caching GA4 account summaries.
	 *
	 * @var string
	 */
	public const PROPERTIES_CACHE_KEY = 'outstand_query_loop_analytics_properties';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_filter( 'pre_update_option_' . self::OPTION_SETTINGS, [ $this, 'normalize_for_storage' ], PHP_INT_MAX, 1 );
	}

	/**
	 * Normalize any write to OPTION_SETTINGS: encrypt client_secret, validate all
	 * fields. Guarantees storage invariants regardless of write path.
	 *
	 * @param mixed $value Value about to be written.
	 * @return mixed
	 */
	public function normalize_for_storage( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$defaults = self::get_defaults();

		// Strip constant-backed credentials so runtime constants never leak into the DB.
		if ( defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID' ) ) {
			$value['client_id'] = '';
		}
		if ( defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET' ) ) {
			$value['client_secret'] = '';
		}

		// client_secret: must be encrypted (enc:v1:-tagged).
		$secret = (string) ( $value['client_secret'] ?? '' );
		if ( $secret !== '' && strpos( $secret, 'enc:v1:' ) !== 0 ) {
			$value['client_secret'] = self::encrypt_secret( $secret );
		}

		// client_id: sanitize.
		$value['client_id'] = sanitize_text_field( (string) ( $value['client_id'] ?? '' ) );

		// property_id: numeric only.
		$value['property_id'] = self::sanitize_property_id( $value['property_id'] ?? '' );

		// Numeric settings: absint + clamp.
		$value['date_range_days'] = max( 1, min( 365, absint( $value['date_range_days'] ?? $defaults['date_range_days'] ) ) );
		$value['fetch_limit']     = max( 1, min( 100, absint( $value['fetch_limit'] ?? $defaults['fetch_limit'] ) ) );
		$value['cache_duration']  = max( 1, min( 168, absint( $value['cache_duration'] ?? $defaults['cache_duration'] ) ) );

		return $value;
	}

	/**
	 * Add the settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		$hook = add_options_page(
			__( 'Outstand Query Loop Analytics', 'outstand-query-loop-analytics' ),
			__( 'Query Loop Analytics', 'outstand-query-loop-analytics' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, [ $this, 'add_help_tabs' ] );
		}
	}

	/**
	 * Register contextual help tabs on the settings screen.
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			[
				'id'      => 'outstand_qla_help_google_api',
				'title'   => __( 'Google API Setup', 'outstand-query-loop-analytics' ),
				'content' => $this->get_google_api_help(),
			]
		);

		$screen->add_help_tab(
			[
				'id'      => 'outstand_qla_help_popular_posts',
				'title'   => __( 'Popular Posts Settings', 'outstand-query-loop-analytics' ),
				'content' => $this->get_popular_posts_help(),
			]
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'outstand-query-loop-analytics' ) . '</strong></p>' .
			'<p><a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud Console', 'outstand-query-loop-analytics' ) . '</a></p>' .
			'<p><a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Analytics', 'outstand-query-loop-analytics' ) . '</a></p>'
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_SETTINGS,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => self::get_defaults(),
				'autoload'          => false,
			]
		);

		// One-shot migration: force non-autoload on legacy rows.
		if ( ! get_option( 'outstand_query_loop_analytics_autoload_migrated' ) ) {
			if ( function_exists( 'wp_set_option_autoload' ) ) {
				wp_set_option_autoload( self::OPTION_SETTINGS, false );
			}
			update_option( 'outstand_query_loop_analytics_autoload_migrated', 1, false );
		}

		add_settings_section(
			'app_settings',
			__( 'Google API', 'outstand-query-loop-analytics' ),
			null,
			self::PAGE_SLUG
		);

		// Credentials fields (hidden when constants are defined).
		if ( ! self::has_credential_constants() ) {
			add_settings_field(
				'client_id',
				__( 'Client ID', 'outstand-query-loop-analytics' ),
				[ $this, 'render_text_field' ],
				self::PAGE_SLUG,
				'app_settings',
				[
					'label_for' => 'client_id',
					'key'       => 'client_id',
					'desc'      => __( 'Your Google Cloud OAuth 2.0 Client ID. See the <strong>Google API Setup</strong> help tab (top right) for steps.', 'outstand-query-loop-analytics' ),
				]
			);

			add_settings_field(
				'client_secret',
				__( 'Client Secret', 'outstand-query-loop-analytics' ),
				[ $this, 'render_text_field' ],
				self::PAGE_SLUG,
				'app_settings',
				[
					'label_for' => 'client_secret',
					'key'       => 'client_secret',
					'type'      => 'password',
					'desc'      => __( 'The OAuth 2.0 Client Secret paired with the Client ID above.', 'outstand-query-loop-analytics' ),
				]
			);
		}

		// GA4 property picker (only when connected) — sits with credentials, above Status.
		if ( self::get_tokens() ) {
			add_settings_field(
				'property_picker',
				__( 'GA4 Property', 'outstand-query-loop-analytics' ),
				[ $this, 'render_property_picker_field' ],
				self::PAGE_SLUG,
				'app_settings',
				[
					'label_for' => 'outstand_query_loop_analytics_property_id',
				]
			);
		}

		// Auth status field (connect / disconnect button inline with status).
		add_settings_field(
			'auth_status',
			__( 'Status', 'outstand-query-loop-analytics' ),
			[ $this, 'render_auth_status_field' ],
			self::PAGE_SLUG,
			'app_settings',
			[
				'label_for' => 'auth_status',
			]
		);

		// Configuration section.
		add_settings_section(
			'configuration',
			__( 'Popular Posts', 'outstand-query-loop-analytics' ),
			'__return_null',
			self::PAGE_SLUG
		);

		add_settings_field(
			'date_range_days',
			__( 'Date Range (days)', 'outstand-query-loop-analytics' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'configuration',
			[
				'label_for' => 'date_range_days',
				'key'       => 'date_range_days',
				'min'       => 1,
				'max'       => 365,
				'desc'      => __( 'How far back pageviews are counted when ranking posts. Lower favors trending posts, higher favors all-time popular.', 'outstand-query-loop-analytics' ),
			]
		);

		add_settings_field(
			'fetch_limit',
			__( 'Maximum Posts to Fetch', 'outstand-query-loop-analytics' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'configuration',
			[
				'label_for' => 'fetch_limit',
				'key'       => 'fetch_limit',
				'min'       => 1,
				'max'       => 100,
				'desc'      => __( 'How many top posts are fetched from Analytics and cached. A Query Loop can show up to this many results.', 'outstand-query-loop-analytics' ),
			]
		);

		add_settings_field(
			'cache_duration',
			__( 'Cache Duration (hours)', 'outstand-query-loop-analytics' ),
			[ $this, 'render_number_field' ],
			self::PAGE_SLUG,
			'configuration',
			[
				'label_for' => 'cache_duration',
				'key'       => 'cache_duration',
				'min'       => 1,
				'max'       => 168,
				'desc'      => __( 'How long fetched Analytics data is cached before the next background refresh. Higher means fewer API calls but less fresh data.', 'outstand-query-loop-analytics' ),
			]
		);
	}

	/**
	 * Handle connect, disconnect, and property selection actions.
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_disconnect();
	}

	/**
	 * Handle the disconnect action.
	 */
	private function handle_disconnect(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'outstand_query_loop_analytics_disconnect' ) {
			return;
		}

		check_admin_referer( 'outstand_query_loop_analytics_disconnect' );

		$revoke_failed = false;
		$tokens        = self::get_tokens();
		if ( $tokens && ! empty( $tokens['access_token'] ) ) {
			$settings = self::get_settings();
			$client   = new GoogleClient(
				$settings['client_id'],
				$settings['client_secret'],
				Auth::get_redirect_uri()
			);
			if ( ! $client->revoke_token( $tokens['access_token'] ) ) {
				$revoke_failed = true;
				Logger::error( 'disconnect: Google token revocation failed' );
			}
		}

		delete_option( self::OPTION_TOKENS );
		delete_option( Analytics::CACHE_KEY );
		delete_transient( Analytics::ERROR_BACKOFF_KEY );
		delete_transient( Analytics::LAST_SYNC_KEY );
		delete_transient( self::PROPERTIES_CACHE_KEY );
		Analytics::unschedule();

		$settings                = self::get_settings();
		$settings['property_id'] = '';
		update_option( self::OPTION_SETTINGS, $settings );

		$redirect_args = [ 'page' => self::PAGE_SLUG ];
		if ( $revoke_failed ) {
			$redirect_args['error'] = 'disconnect_partial';
		} else {
			$redirect_args['updated'] = 'disconnected';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_admin_notices(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render contextual admin notices.
	 */
	private function render_admin_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';

		if ( $updated === 'connected' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Successfully connected to Google Analytics.', 'outstand-query-loop-analytics' ) . '</p></div>';
		} elseif ( $updated === 'disconnected' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Disconnected from Google Analytics.', 'outstand-query-loop-analytics' ) . '</p></div>';
		}

		if ( $error === 'oauth_denied' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Google authorization was denied.', 'outstand-query-loop-analytics' ) . '</p></div>';
		} elseif ( $error === 'oauth_failed' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to connect to Google Analytics. Please try again.', 'outstand-query-loop-analytics' ) . '</p></div>';
		} elseif ( $error === 'oauth_expired' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Authorization link expired. Please start the connection again.', 'outstand-query-loop-analytics' ) . '</p></div>';
		} elseif ( $error === 'disconnect_partial' ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Disconnected locally, but Google token revocation failed. The token may still be valid at Google.', 'outstand-query-loop-analytics' ) . '</p></div>';
		}

		if ( self::has_credential_constants() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Client ID and Client Secret are defined via PHP constants and cannot be edited from this screen.', 'outstand-query-loop-analytics' ) . '</p></div>';
		}
	}

	/**
	 * Render the authorization status field — status text + connect/disconnect button inline.
	 */
	public function render_auth_status_field(): void {
		$settings = self::get_settings();
		$tokens   = self::get_tokens();

		// No credentials yet — show waiting state.
		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			printf(
				'<div style="display: flex; align-items: center; gap: 12px;"><span>%s</span></div>',
				esc_html__( '⏳ Waiting for credentials', 'outstand-query-loop-analytics' )
			);
			return;
		}

		// Credentials present, no tokens — show connect button.
		if ( ! $tokens ) {
			$client   = new GoogleClient(
				$settings['client_id'],
				$settings['client_secret'],
				Auth::get_redirect_uri()
			);
			$state    = wp_create_nonce( 'outstand_query_loop_analytics_oauth' );
			$auth_url = $client->get_auth_url( $state );

			printf(
				'<div style="display: flex; align-items: center; gap: 12px;">
					<span>%1$s</span>
					<a href="%2$s" class="button button-primary">%3$s</a>
				</div>',
				esc_html__( '⏳ Not Connected', 'outstand-query-loop-analytics' ),
				esc_url( $auth_url ),
				esc_html__( 'Connect to Google Analytics', 'outstand-query-loop-analytics' )
			);
			return;
		}

		// Connected — show status + disconnect button.
		$disconnect_url = wp_nonce_url(
			add_query_arg(
				[
					'page'   => self::PAGE_SLUG,
					'action' => 'outstand_query_loop_analytics_disconnect',
				],
				admin_url( 'options-general.php' )
			),
			'outstand_query_loop_analytics_disconnect'
		);

		printf(
			'<div style="display: flex; align-items: center; gap: 12px;">
				<span>%1$s</span>
				<a href="%2$s" class="button" onclick="return confirm(\'%3$s\');">%4$s</a>
			</div>',
			esc_html__( '✅ Connected', 'outstand-query-loop-analytics' ),
			esc_url( $disconnect_url ),
			esc_js( __( 'Are you sure you want to disconnect?', 'outstand-query-loop-analytics' ) ),
			esc_html__( 'Disconnect', 'outstand-query-loop-analytics' )
		);
	}

	/**
	 * Render the GA4 property picker as a settings field.
	 */
	public function render_property_picker_field(): void {
		$settings = self::get_settings();
		$tokens   = self::get_tokens();

		if ( ! $tokens ) {
			return;
		}

		$client = new GoogleClient(
			$settings['client_id'],
			$settings['client_secret'],
			Auth::get_redirect_uri()
		);
		$client->set_access_token( $tokens );

		if ( $client->is_token_expired() && ! empty( $tokens['refresh_token'] ) ) {
			$new_tokens = $client->refresh_token( $tokens['refresh_token'] );
			if ( ! is_wp_error( $new_tokens ) ) {
				self::set_tokens( $new_tokens );
				$client->set_access_token( $new_tokens );
			}
		}

		$summaries = get_transient( self::PROPERTIES_CACHE_KEY );
		if ( ! is_array( $summaries ) ) {
			$summaries = $client->get_account_summaries();
			if ( ! is_wp_error( $summaries ) ) {
				set_transient( self::PROPERTIES_CACHE_KEY, $summaries, 5 * MINUTE_IN_SECONDS );
			}
		}

		if ( is_wp_error( $summaries ) ) {
			printf(
				'<p class="notice notice-error">%s %s</p>',
				esc_html__( 'Failed to load properties:', 'outstand-query-loop-analytics' ),
				esc_html( $summaries->get_error_message() )
			);
			return;
		}

		$current_property = $settings['property_id'] ?? '';
		?>
		<select name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[property_id]" id="outstand_query_loop_analytics_property_id">
			<option value=""><?php esc_html_e( '— Select a property —', 'outstand-query-loop-analytics' ); ?></option>
			<?php foreach ( $summaries as $account ) : ?>
				<optgroup label="<?php echo esc_attr( $account['account'] ); ?>">
					<?php foreach ( $account['properties'] as $property ) : ?>
						<option value="<?php echo esc_attr( $property['id'] ); ?>" <?php selected( $current_property, $property['id'] ); ?>>
							<?php echo esc_html( $property['name'] . ' (' . $property['id'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
		<?php
		$this->render_field_description(
			[
				'desc' => __( 'The Analytics property to read pageviews from. Saved with the settings below.', 'outstand-query-loop-analytics' ),
			]
		);
	}

	/**
	 * Render a text input field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_text_field( array $args ): void {
		$settings = self::get_settings();
		$key      = $args['key'];
		$type     = $args['type'] ?? 'text';
		$value    = (string) ( $settings[ $key ] ?? '' );

		// Never pre-fill password fields; show a "set" indicator instead.
		if ( $type === 'password' ) {
			$has_value   = $value !== '';
			$placeholder = $has_value
				? __( '•••••••• (set — leave blank to keep)', 'outstand-query-loop-analytics' )
				: __( 'Enter value', 'outstand-query-loop-analytics' );

			printf(
				'<input type="password" id="%s" name="%s[%s]" value="" placeholder="%s" autocomplete="new-password" class="regular-text" />',
				esc_attr( $args['label_for'] ),
				esc_attr( self::OPTION_SETTINGS ),
				esc_attr( $key ),
				esc_attr( $placeholder )
			);
			$this->render_field_description( $args );
			return;
		}

		printf(
			'<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
			esc_attr( $type ),
			esc_attr( $args['label_for'] ),
			esc_attr( self::OPTION_SETTINGS ),
			esc_attr( $key ),
			esc_attr( $value )
		);
		$this->render_field_description( $args );
	}

	/**
	 * Render a number input field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_number_field( array $args ): void {
		$settings = self::get_settings();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? self::get_defaults()[ $key ] ?? '';

		printf(
			'<input type="number" id="%s" name="%s[%s]" value="%s" min="%s" max="%s" class="small-text" />',
			esc_attr( $args['label_for'] ),
			esc_attr( self::OPTION_SETTINGS ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $args['min'] ?? 0 ),
			esc_attr( $args['max'] ?? 999 )
		);
		$this->render_field_description( $args );
	}

	/**
	 * Render a field's description text, if provided.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_field_description( array $args ): void {
		$desc = (string) ( $args['desc'] ?? '' );
		if ( $desc === '' ) {
			return;
		}

		printf( '<p class="description">%s</p>', wp_kses_post( $desc ) );
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return self::get_defaults();
		}

		$current  = self::get_settings();
		$defaults = self::get_defaults();

		$client_id = sanitize_text_field( $input['client_id'] ?? $current['client_id'] );

		// Empty submitted secret = keep existing (password field is never prefilled).
		$submitted_secret = isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : '';
		$client_secret    = $submitted_secret !== '' ? $submitted_secret : $current['client_secret'];

		// Invalidate caches and stale tokens when credentials change.
		if ( $client_id !== $current['client_id'] || $client_secret !== $current['client_secret'] ) {
			delete_option( self::OPTION_TOKENS );
			delete_option( Analytics::CACHE_KEY );
			delete_transient( self::PROPERTIES_CACHE_KEY );
			delete_transient( Analytics::ERROR_BACKOFF_KEY );
			delete_transient( Analytics::LAST_SYNC_KEY );
			Analytics::unschedule();
		}

		$property_id = self::sanitize_property_id( $input['property_id'] ?? $current['property_id'] );
		$this->handle_property_change( (string) $current['property_id'], $property_id );

		return [
			'client_id'       => $client_id,
			'client_secret'   => self::encrypt_secret( $client_secret ),
			'property_id'     => $property_id,
			'date_range_days' => absint( $input['date_range_days'] ?? $defaults['date_range_days'] ),
			'fetch_limit'     => absint( $input['fetch_limit'] ?? $defaults['fetch_limit'] ),
			'cache_duration'  => absint( $input['cache_duration'] ?? $defaults['cache_duration'] ),
		];
	}

	/**
	 * React to a change of the selected GA4 property during a settings save:
	 * clear cached data and, when a property is set, refresh tokens and
	 * schedule an immediate sync.
	 *
	 * @param string $old_property Previously stored property ID.
	 * @param string $new_property Newly submitted property ID.
	 */
	private function handle_property_change( string $old_property, string $new_property ): void {
		if ( $old_property === $new_property ) {
			return;
		}

		// Clear cached data when the property changes.
		delete_option( Analytics::CACHE_KEY );
		delete_transient( Analytics::ERROR_BACKOFF_KEY );
		delete_transient( Analytics::LAST_SYNC_KEY );

		if ( $new_property === '' ) {
			return;
		}

		// Proactively refresh tokens so the next cron doesn't fail on a stale token.
		$tokens = self::get_tokens();
		if ( $tokens ) {
			$settings = self::get_settings();
			$client   = new GoogleClient(
				$settings['client_id'],
				$settings['client_secret'],
				Auth::get_redirect_uri()
			);
			$client->set_access_token( $tokens );
			if ( $client->is_token_expired() && ! empty( $tokens['refresh_token'] ) ) {
				$new_tokens = $client->refresh_token( $tokens['refresh_token'] );
				if ( ! is_wp_error( $new_tokens ) ) {
					self::set_tokens( $new_tokens );
				} else {
					Logger::error( 'property_change_token_refresh_failed: ' . $new_tokens->get_error_message() );
				}
			}
		}

		if ( ! wp_next_scheduled( Analytics::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 10, Analytics::CRON_HOOK );
		}
	}

	/**
	 * Encrypt a secret for storage, returning an `enc:v1:` tagged ciphertext.
	 *
	 * @param string $plaintext Plain secret.
	 * @return string Encrypted string or empty string.
	 */
	private static function encrypt_secret( string $plaintext ): string {
		if ( $plaintext === '' ) {
			return '';
		}
		$cipher = Encryption::encrypt( $plaintext );
		if ( $cipher === false ) {
			Logger::error( 'encrypt_secret: encryption failed; storing empty' );
			return '';
		}
		return 'enc:v1:' . $cipher;
	}

	/**
	 * Decrypt a stored secret. Handles legacy plaintext transparently.
	 *
	 * @param string $value Stored value.
	 * @return string Plain secret.
	 */
	private static function decrypt_secret( string $value ): string {
		if ( $value === '' ) {
			return '';
		}
		if ( strpos( $value, 'enc:v1:' ) !== 0 ) {
			return $value; // Legacy plaintext.
		}
		$decrypted = Encryption::decrypt( substr( $value, 7 ) );
		if ( $decrypted === false ) {
			Logger::error( 'decrypt_secret: failed; returning empty' );
			return '';
		}
		return $decrypted;
	}

	/**
	 * Build the Google API setup help tab content.
	 *
	 * @return string
	 */
	private function get_google_api_help(): string {
		$redirect_uri = Auth::get_redirect_uri();

		$steps = [
			__( 'In the Google Cloud Console, create (or select) a project.', 'outstand-query-loop-analytics' ),
			__( 'Enable the <strong>Google Analytics Data API</strong> and the <strong>Google Analytics Admin API</strong> for that project.', 'outstand-query-loop-analytics' ),
			__( 'Open <strong>APIs &amp; Services → OAuth consent screen</strong>, configure it, and add your account as a test user.', 'outstand-query-loop-analytics' ),
			__( 'Open <strong>APIs &amp; Services → Credentials</strong> and create an <strong>OAuth client ID</strong> of type <strong>Web application</strong>.', 'outstand-query-loop-analytics' ),
			sprintf(
				/* translators: %s: authorized redirect URI. */
				__( 'Under <strong>Authorized redirect URIs</strong>, add exactly: %s', 'outstand-query-loop-analytics' ),
				'<code>' . esc_html( $redirect_uri ) . '</code>'
			),
			__( 'Copy the generated <strong>Client ID</strong> and <strong>Client Secret</strong> into the fields on this screen, then save and click <strong>Connect to Google Analytics</strong>.', 'outstand-query-loop-analytics' ),
		];

		$html  = '<p>' . esc_html__( 'To pull data from Google Analytics, create OAuth credentials in the Google Cloud Console:', 'outstand-query-loop-analytics' ) . '</p>';
		$html .= '<ol>';
		foreach ( $steps as $step ) {
			$html .= '<li>' . wp_kses_post( $step ) . '</li>';
		}
		$html .= '</ol>';
		$html .= '<p><a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open the Google Cloud Console credentials page →', 'outstand-query-loop-analytics' ) . '</a></p>';

		return $html;
	}

	/**
	 * Build the Popular Posts settings help tab content.
	 *
	 * @return string
	 */
	private function get_popular_posts_help(): string {
		$items = [
			[
				__( 'GA4 Property', 'outstand-query-loop-analytics' ),
				__( 'The Analytics property the plugin reads pageviews from. Only shown once connected.', 'outstand-query-loop-analytics' ),
			],
			[
				__( 'Date Range (days)', 'outstand-query-loop-analytics' ),
				__( 'How far back pageviews are counted when ranking posts. Lower = trending/recent, higher = all-time favorites.', 'outstand-query-loop-analytics' ),
			],
			[
				__( 'Maximum Posts to Fetch', 'outstand-query-loop-analytics' ),
				__( 'How many top posts are retrieved from Analytics and cached. A Query Loop can show up to this many results.', 'outstand-query-loop-analytics' ),
			],
			[
				__( 'Cache Duration (hours)', 'outstand-query-loop-analytics' ),
				__( 'How long fetched Analytics data is stored before the next background refresh. Higher = fewer API calls, less fresh data.', 'outstand-query-loop-analytics' ),
			],
		];

		$html  = '<p>' . esc_html__( 'These settings control how popular posts are calculated and cached:', 'outstand-query-loop-analytics' ) . '</p>';
		$html .= '<dl>';
		foreach ( $items as $item ) {
			$html .= '<dt><strong>' . esc_html( $item[0] ) . '</strong></dt>';
			$html .= '<dd>' . esc_html( $item[1] ) . '</dd>';
		}
		$html .= '</dl>';

		return $html;
	}

	/**
	 * Sanitize a GA4 property ID. Accepts numeric or "properties/<digits>".
	 *
	 * @param mixed $value Raw value.
	 * @return string Normalized numeric property ID, or empty string if invalid.
	 */
	public static function sanitize_property_id( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		$value = preg_replace( '#^properties/#', '', $value );
		return ctype_digit( $value ) ? $value : '';
	}

	/**
	 * Get the current settings, merging with defaults and constants.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = (array) get_option( self::OPTION_SETTINGS, [] );
		$settings = wp_parse_args( $settings, self::get_defaults() );

		// Decrypt stored secret (transparent legacy plaintext support).
		$settings['client_secret'] = self::decrypt_secret( (string) ( $settings['client_secret'] ?? '' ) );

		// Constants override stored values.
		if ( defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID' ) ) {
			$settings['client_id'] = OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID;
		}
		if ( defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET' ) ) {
			$settings['client_secret'] = OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET;
		}

		return $settings;
	}

	/**
	 * Get the default settings values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return [
			'client_id'       => '',
			'client_secret'   => '',
			'property_id'     => '',
			'date_range_days' => 30,
			'fetch_limit'     => 20,
			'cache_duration'  => 12,
		];
	}

	/**
	 * Check whether credential constants are defined.
	 *
	 * @return bool
	 */
	public static function has_credential_constants(): bool {
		return defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID' ) && defined( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET' );
	}

	/**
	 * Get the stored OAuth tokens (decrypted).
	 *
	 * @return array<string, mixed>|null Token array or null if not set.
	 */
	public static function get_tokens(): ?array {
		$encrypted = get_option( self::OPTION_TOKENS );

		if ( empty( $encrypted ) ) {
			return null;
		}

		$decrypted = Encryption::decrypt( $encrypted );

		if ( $decrypted === false ) {
			Logger::error( 'get_tokens: decryption failed (key rotated or corrupted ciphertext)' );
			return null;
		}

		$tokens = json_decode( $decrypted, true );

		if ( ! is_array( $tokens ) ) {
			Logger::error( 'get_tokens: json_decode failed on decrypted tokens' );
			return null;
		}

		return $tokens;
	}

	/**
	 * Store OAuth tokens (encrypted).
	 *
	 * @param array<string, mixed> $tokens Token array.
	 * @return bool True on success, false on failure.
	 */
	public static function set_tokens( array $tokens ): bool {
		$json = wp_json_encode( $tokens );

		if ( $json === false ) {
			Logger::error( 'set_tokens: json_encode failed' );
			return false;
		}

		$encrypted = Encryption::encrypt( $json );
		if ( $encrypted === false ) {
			return false;
		}

		return (bool) update_option( self::OPTION_TOKENS, $encrypted, false );
	}

	/**
	 * Get the configured GA4 property ID.
	 *
	 * @return string
	 */
	public static function get_property_id(): string {
		$settings = self::get_settings();
		return $settings['property_id'] ?? '';
	}

	/**
	 * Check if the plugin is connected and has a property selected.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return self::get_tokens() !== null && self::get_property_id() !== '';
	}
}
