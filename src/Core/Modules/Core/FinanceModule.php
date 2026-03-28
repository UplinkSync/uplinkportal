<?php
/**
 * Core finance module.
 *
 * @package UplinkPortal\Core\Modules\Core
 */

namespace UplinkPortal\Core\Modules\Core;

use UplinkPortal\Core\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Free core module for finance workflows.
 */
class FinanceModule implements ModuleInterface {
	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'finance';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Revenue & Billing', 'uplinkportal' );
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
			'client_view_billing',
		);
	}
}
