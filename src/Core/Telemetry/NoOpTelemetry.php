<?php
/**
 * No-op telemetry implementation.
 *
 * @package UplinkPortal\Core\Telemetry
 */

namespace UplinkPortal\Core\Telemetry;

defined( 'ABSPATH' ) || exit;

/**
 * Default telemetry implementation that intentionally does nothing.
 */
class NoOpTelemetry implements TelemetryInterface {
	/**
	 * Whether telemetry emission is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return false;
	}

	/**
	 * Records one telemetry event.
	 *
	 * @param string              $event_name Event name.
	 * @param array<string,mixed> $context Event context.
	 * @return void
	 */
	public function record_event( string $event_name, array $context = array() ): void {
		// Intentionally no-op in public core defaults.
	}
}
