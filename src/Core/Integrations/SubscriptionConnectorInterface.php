<?php
/**
 * Subscription connector contract.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Connector contract for subscription providers.
 */
interface SubscriptionConnectorInterface {
	/**
	 * Returns subscriptions for current user context.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_subscriptions_for_user( int $user_id ): array;
}
