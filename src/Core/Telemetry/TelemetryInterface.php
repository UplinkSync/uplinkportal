<?php
/**
 * Telemetry contract.
 *
 * @package UplinkPortal\Core\Telemetry
 */

namespace UplinkPortal\Core\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Defines telemetry behavior for runtime events.
 */
interface TelemetryInterface {
	/**
	 * Whether telemetry emission is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Records one telemetry event.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $context Event context.
	 * @return void
	 */
	public function record_event( string $event_name, array $context = array() ): void;
}
