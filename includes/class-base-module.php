<?php
/**
 * Base module for shared hook and asset helpers.
 *
 * @package UplinkPortal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Common reusable behavior for include-layer modules.
 */
abstract class UplinkPortal_Base_Module {
	/**
	 * Registers a WordPress action callback.
	 *
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return void
	 */
	protected function register_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_action( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Registers a WordPress filter callback.
	 *
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return void
	 */
	protected function register_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Builds an asset URL from plugin root.
	 *
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	protected function asset_url( string $relative_path ): string {
		return plugins_url( ltrim( $relative_path, '/' ), UPLINKPORTAL_FILE );
	}

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	abstract public function register(): void;
}
