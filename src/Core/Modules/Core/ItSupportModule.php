<?php
/**
 * Core IT support module.
 *
 * @package UplinkPortal\Core\Modules\Core
 */

namespace UplinkPortal\Core\Modules\Core;

use UplinkPortal\Core\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Free core module for support workflows.
 */
class ItSupportModule implements ModuleInterface {
	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'it_support';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Support Queue', 'uplinkportal' );
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function register(): void {
		// Intentionally minimal in public core module scaffold.
	}

	/**
	 * Module enabled state.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return true;
	}

	/**
	 * Capability requirements.
	 *
	 * @return array<int,string>
	 */
	public function capabilities_required(): array {
		return array(
			'client_access',
			'client_view_tickets',
		);
	}
}
