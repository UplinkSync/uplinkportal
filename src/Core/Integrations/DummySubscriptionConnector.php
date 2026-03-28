<?php
/**
 * Dummy subscription connector.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Mock connector for public scaffolding.
 */
class DummySubscriptionConnector implements SubscriptionConnectorInterface {
	/**
	 * Returns mock subscriptions.
	 *
	 * @param int $user_id User id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_subscriptions_for_user( int $user_id ): array {
		return array(
			array(
				'id'          => 'SUB-1001',
				'product'     => __( 'Managed Hosting Plan', 'uplinkportal' ),
				'status'      => 'active',
				'renewalDate' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ),
				'user_id'     => $user_id,
				'provider'    => 'dummy',
			),
		);
	}
}
