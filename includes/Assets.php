<?php

namespace Outstand\WP\QueryLoop\Analytics;

class Assets extends BaseModule {
	use GetAssetInfo;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->setup_asset_vars(
			dist_path: OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_PATH,
			fallback_version: OUTSTAND_QUERY_LOOP_ANALYTICS_VERSION
		);

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
		add_filter( 'plugin_action_links_' . OUTSTAND_QUERY_LOOP_ANALYTICS_BASENAME, [ $this, 'add_action_links' ] );
	}

	/**
	 * Add Settings link to plugin action links.
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string>
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'outstand-query-loop-analytics' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enqueue block editor scripts.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_scripts(): void {
		wp_enqueue_script(
			'outstand-query-loop-analytics-block-editor',
			OUTSTAND_QUERY_LOOP_ANALYTICS_DIST_URL . 'js/block-editor.js',
			$this->get_asset_info( 'block-editor', 'dependencies' ),
			$this->get_asset_info( 'block-editor', 'version' ),
			true
		);

		wp_set_script_translations(
			'outstand-query-loop-analytics-block-editor',
			'outstand-query-loop-analytics',
			OUTSTAND_QUERY_LOOP_ANALYTICS_PATH . 'languages'
		);
	}
}
