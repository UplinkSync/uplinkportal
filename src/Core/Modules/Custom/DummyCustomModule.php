<?php
/**
 * Dummy custom module for toggle testing.
 *
 * @package UplinkPortal\Core\Modules\Custom
 */

namespace UplinkPortal\Core\Modules\Custom;

use UplinkPortal\Core\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Example custom module scaffold.
 */
class DummyCustomModule implements ModuleInterface {
	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'custom_dummy';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Custom Workspace (Dummy)', 'uplinkportal' );
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Public scaffold only.
	}

	/**
	 * Whether module is internally enabled.
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
		return array( 'client_access' );
	}
}
