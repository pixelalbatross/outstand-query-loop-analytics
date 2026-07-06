<?php

namespace Outstand\WP\QueryLoop\Analytics;

use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Client;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\DateRange;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\Dimension;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\Metric;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\MetricOrderBy;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\OrderBy;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\AnalyticsData\RunReportRequest;
use Outstand\WP\QueryLoop\Analytics\Dependencies\Google\Service\GoogleAnalyticsAdmin;
use WP_Error;

/**
 * Wrapper around Google API client for OAuth and GA4 data access.
 */
class GoogleClient {

	/**
	 * The Google API client instance.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Constructor.
	 *
	 * @param string $client_id     OAuth client ID.
	 * @param string $client_secret OAuth client secret.
	 * @param string $redirect_uri  OAuth redirect URI.
	 */
	public function __construct( string $client_id, string $client_secret, string $redirect_uri ) {
		$this->client = new Client();
		$this->client->setClientId( $client_id );
		$this->client->setClientSecret( $client_secret );
		$this->client->setRedirectUri( $redirect_uri );
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );
		$this->client->addScope( AnalyticsData::ANALYTICS_READONLY );
		$this->client->setApplicationName( 'Outstand Query Loop Analytics' );
	}

	/**
	 * Get the OAuth authorization URL.
	 *
	 * @param string $state CSRF state token.
	 * @return string The authorization URL.
	 */
	public function get_auth_url( string $state ): string {
		$this->client->setState( $state );
		return $this->client->createAuthUrl();
	}

	/**
	 * Exchange an authorization code for tokens.
	 *
	 * @param string $code The authorization code.
	 * @return array<string, mixed>|WP_Error Token array or error.
	 */
	public function fetch_access_token( string $code ): array|WP_Error {
		try {
			$token = $this->client->fetchAccessTokenWithAuthCode( $code );
		} catch ( \Exception $e ) {
			return new WP_Error( 'oauth_exchange_failed', $e->getMessage() );
		}

		if ( isset( $token['error'] ) ) {
			return new WP_Error(
				'oauth_exchange_failed',
				$token['error_description'] ?? $token['error']
			);
		}

		if ( empty( $token['refresh_token'] ) ) {
			return new WP_Error(
				'oauth_no_refresh_token',
				__( 'No refresh token received. Try disconnecting and reconnecting.', 'outstand-query-loop-analytics' )
			);
		}

		return $token;
	}

	/**
	 * Set the access token on the client.
	 *
	 * @param array<string, mixed> $token Token array.
	 */
	public function set_access_token( array $token ): void {
		$this->client->setAccessToken( $token );
	}

	/**
	 * Check if the current access token is expired.
	 *
	 * @return bool
	 */
	public function is_token_expired(): bool {
		return $this->client->isAccessTokenExpired();
	}

	/**
	 * Refresh the access token using a refresh token.
	 *
	 * @param string $refresh_token The refresh token.
	 * @return array<string, mixed>|WP_Error New token array or error.
	 */
	public function refresh_token( string $refresh_token ): array|WP_Error {
		try {
			$this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
			$new_token = $this->client->getAccessToken();
		} catch ( \Exception $e ) {
			return new WP_Error( 'token_refresh_failed', $e->getMessage() );
		}

		if ( empty( $new_token['access_token'] ) ) {
			return new WP_Error( 'token_refresh_failed', __( 'Failed to refresh access token.', 'outstand-query-loop-analytics' ) );
		}

		$new_token['refresh_token'] = $refresh_token;

		return $new_token;
	}

	/**
	 * Revoke the current token.
	 *
	 * @param string $token The token to revoke.
	 * @return bool Whether revocation succeeded.
	 */
	public function revoke_token( string $token ): bool {
		try {
			return $this->client->revokeToken( $token );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Fetch GA4 account summaries for the property picker.
	 *
	 * @return array<array{account: string, properties: array<array{id: string, name: string}>}>|WP_Error
	 */
	public function get_account_summaries(): array|WP_Error {
		try {
			$admin_service = new GoogleAnalyticsAdmin( $this->client );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Google SDK property name.
			$response  = $admin_service->accountSummaries->listAccountSummaries( [ 'pageSize' => 200 ] );
			$summaries = [];

			foreach ( $response->getAccountSummaries() ?? [] as $account ) {
				$properties = [];
				foreach ( $account->getPropertySummaries() ?? [] as $property ) {
					$property_id  = str_replace( 'properties/', '', $property->getProperty() );
					$properties[] = [
						'id'   => $property_id,
						'name' => $property->getDisplayName(),
					];
				}

				$summaries[] = [
					'account'    => $account->getDisplayName(),
					'properties' => $properties,
				];
			}

			return $summaries;
		} catch ( \Exception $e ) {
			return new WP_Error( 'account_summaries_failed', $e->getMessage() );
		}
	}

	/**
	 * Run a GA4 report for popular pages.
	 *
	 * @param string $property_id GA4 property ID.
	 * @param string $start_date  Start date (YYYY-MM-DD or relative).
	 * @param string $end_date    End date (YYYY-MM-DD or relative).
	 * @param int    $limit       Maximum number of rows.
	 * @return array<array{path: string, pageviews: int}>|WP_Error
	 */
	public function get_popular_pages( string $property_id, string $start_date, string $end_date, int $limit ): array|WP_Error {
		try {
			$data_service = new AnalyticsData( $this->client );

			$date_range = new DateRange();
			$date_range->setStartDate( $start_date );
			$date_range->setEndDate( $end_date );

			$dimension = new Dimension();
			$dimension->setName( 'pagePath' );

			$metric = new Metric();
			$metric->setName( 'screenPageViews' );

			$metric_order_by = new MetricOrderBy();
			$metric_order_by->setMetricName( 'screenPageViews' );

			$order_by = new OrderBy();
			$order_by->setMetric( $metric_order_by );
			$order_by->setDesc( true );

			$request = new RunReportRequest();
			$request->setDateRanges( [ $date_range ] );
			$request->setDimensions( [ $dimension ] );
			$request->setMetrics( [ $metric ] );
			$request->setOrderBys( [ $order_by ] );
			$request->setLimit( $limit );

			$response = $data_service->properties->runReport( "properties/{$property_id}", $request );
			$pages    = [];

			foreach ( $response->getRows() ?? [] as $row ) {
				$path      = $row->getDimensionValues()[0]->getValue();
				$pageviews = (int) $row->getMetricValues()[0]->getValue();

				if ( ! empty( $path ) ) {
					$pages[] = [
						'path'      => $path,
						'pageviews' => $pageviews,
					];
				}
			}

			return $pages;
		} catch ( \Exception $e ) {
			return new WP_Error( 'ga4_report_failed', $e->getMessage() );
		}
	}
}
