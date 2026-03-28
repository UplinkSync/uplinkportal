<?php
/**
 * Dummy ticketing connector.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Mock connector for public scaffolding.
 */
class DummyTicketingConnector implements TicketingConnectorInterface {
	/**
	 * Returns predictable mock data.
	 *
	 * @param int $user_id User id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_tickets_for_user( int $user_id ): array {
		return array(
			array(
				'id'       => 'TCK-1001',
				'subject'  => __( 'Example support ticket', 'uplinkportal' ),
				'status'   => 'open',
				'user_id'  => $user_id,
				'provider' => 'dummy',
			),
		);
	}
}
