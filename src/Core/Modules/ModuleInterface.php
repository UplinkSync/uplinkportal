<?php
/**
 * Module interface.
 *
 * @package UplinkPortal\Core\Modules
 */

namespace UplinkPortal\Core\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Defines contracts for portal modules.
 */
interface ModuleInterface {
	/**
	 * Unique module id.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Registers hooks, routes, and handlers.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Whether module is internally enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Capability requirements for module access.
	 *
	 * @return array<int,string>
	 */
	public function capabilities_required(): array;
}
