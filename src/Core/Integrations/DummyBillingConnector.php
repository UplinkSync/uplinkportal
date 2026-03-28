<?php
/**
 * Dummy billing connector.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Mock connector for public scaffolding.
 */
class DummyBillingConnector implements BillingConnectorInterface {
	/**
	 * Returns mock invoice data.
	 *
	 * @param int $user_id User id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_invoices_for_user( int $user_id ): array {
		return array(
			array(
				'id'       => 'INV-1001',
				'amount'   => 29.00,
				'currency' => 'USD',
				'status'   => 'paid',
				'user_id'  => $user_id,
				'provider' => 'dummy',
			),
		);
	}
}
