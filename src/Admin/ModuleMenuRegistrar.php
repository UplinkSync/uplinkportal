<?php
/**
 * Module admin menu registrar.
 *
 * @package UplinkPortal\Admin
 */

namespace UplinkPortal\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized, strict registrar for module-provided admin menu entries.
 */
class ModuleMenuRegistrar {
	/**
	 * Allowed module menu locations.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_LOCATIONS = array(
		'operations',
		'agreements',
		'service_catalog',
		'configuration',
		'hidden',
	);

	/**
	 * Registers module menu entries using one predictable policy gate.
	 *
	 * @param string            $root_menu_slug Root UplinkPortal menu slug.
	 * @param string            $modules_tab_url Configuration > Modules tab URL.
	 * @param array<int,string> $reserved_slugs Core slugs that cannot be overridden.
	 * @param callable          $is_module_enabled Module enabled callback accepting module key.
	 * @return void
	 */
	public function register( string $root_menu_slug, string $modules_tab_url, array $reserved_slugs, callable $is_module_enabled ): void {
		$entries = apply_filters( 'uplinkportal_module_menu_entries', array() );
		if ( ! is_array( $entries ) ) {
			return;
		}

		$seen_slugs = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$normalized = $this->normalize_entry( $entry );
			if ( empty( $normalized ) ) {
				continue;
			}

			$slug       = (string) $normalized['menu_slug'];
			$module_key = (string) $normalized['module_key'];
			$audience   = (string) $normalized['audience'];
			$location   = (string) $normalized['location'];

			if ( 'client' === $audience ) {
				continue;
			}

			if ( '' !== $module_key && ! call_user_func( $is_module_enabled, $module_key ) ) {
				continue;
			}

			if ( in_array( $slug, $reserved_slugs, true ) || in_array( $slug, $seen_slugs, true ) ) {
				continue;
			}

			$seen_slugs[] = $slug;
			$callback     = isset( $normalized['callback'] ) && is_callable( $normalized['callback'] )
				? $normalized['callback']
				: $this->build_modules_tab_redirect_callback( $modules_tab_url );

			if ( in_array( $location, array( 'operations', 'agreements', 'service_catalog' ), true ) ) {
				add_submenu_page(
					$root_menu_slug,
					(string) $normalized['page_title'],
					(string) $normalized['menu_title'],
					(string) $normalized['capability'],
					$slug,
					$callback
				);
				continue;
			}

			add_submenu_page(
				null,
				(string) $normalized['page_title'],
				(string) $normalized['menu_title'],
				(string) $normalized['capability'],
				$slug,
				$callback
			);
		}
	}

	/**
	 * Normalizes one module menu entry payload.
	 *
	 * @param array<string,mixed> $entry Raw entry.
	 * @return array<string,mixed>
	 */
	private function normalize_entry( array $entry ): array {
		$menu_slug = isset( $entry['menu_slug'] ) ? sanitize_key( (string) $entry['menu_slug'] ) : '';
		if ( '' === $menu_slug ) {
			return array();
		}

		$menu_title = isset( $entry['menu_title'] ) ? sanitize_text_field( (string) $entry['menu_title'] ) : '';
		$page_title = isset( $entry['page_title'] ) ? sanitize_text_field( (string) $entry['page_title'] ) : $menu_title;
		if ( '' === $menu_title ) {
			return array();
		}
		if ( '' === $page_title ) {
			$page_title = $menu_title;
		}

		$location = isset( $entry['location'] ) ? sanitize_key( (string) $entry['location'] ) : 'configuration';
		if ( ! in_array( $location, self::ALLOWED_LOCATIONS, true ) ) {
			$location = 'configuration';
		}

		$audience = isset( $entry['audience'] ) ? sanitize_key( (string) $entry['audience'] ) : 'msp';
		if ( ! in_array( $audience, array( 'msp', 'client', 'both' ), true ) ) {
			$audience = 'msp';
		}

		$capability = isset( $entry['capability'] ) ? sanitize_key( (string) $entry['capability'] ) : 'msp_manage';

		return array(
			'module_key' => isset( $entry['module_key'] ) ? sanitize_key( (string) $entry['module_key'] ) : '',
			'menu_slug'  => $menu_slug,
			'menu_title' => $menu_title,
			'page_title' => $page_title,
			'location'   => $location,
			'audience'   => $audience,
			'capability' => '' !== $capability ? $capability : 'msp_manage',
			'callback'   => $entry['callback'] ?? null,
		);
	}

	/**
	 * Builds callback that routes module menu links to Configuration > Modules.
	 *
	 * @param string $modules_tab_url Modules tab URL.
	 * @return callable
	 */
	private function build_modules_tab_redirect_callback( string $modules_tab_url ): callable {
		return static function () use ( $modules_tab_url ): void {
			if ( ! current_user_can( 'msp_manage' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'uplinkportal' ) );
			}

			wp_safe_redirect( $modules_tab_url );
			exit;
		};
	}
}
