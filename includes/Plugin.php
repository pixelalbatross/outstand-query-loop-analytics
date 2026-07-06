<?php

namespace Outstand\WP\QueryLoop\Analytics;

class Plugin {

	/**
	 * Singleton instance of the Plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Returns singleton instance.
	 *
	 * @return Plugin The singleton instance.
	 */
	public static function get_instance(): Plugin {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enable plugin functionality.
	 *
	 * @return void
	 */
	public function enable(): void {

		$modules = [
			new Assets(),
			new Auth(),
			new Settings(),
			new Analytics(),
			new QueryPopularPosts(),
			new BlockAttributes(),
			new Patterns(),
		];

		foreach ( $modules as $module ) {
			if ( $module instanceof BaseModule && $module->can_register() ) {
				$module->register();
			}
		}
	}

	/**
	 * Activation callback.
	 */
	public static function on_activation(): void {
		if ( Settings::is_configured() && ! wp_next_scheduled( Analytics::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, Analytics::CRON_SCHEDULE, Analytics::CRON_HOOK );
		}
	}

	/**
	 * Deactivation callback.
	 */
	public static function on_deactivation(): void {
		Analytics::unschedule();
	}
}
