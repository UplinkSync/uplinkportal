<?php
/**
 * Ticketing connector contract.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Connector contract for ticketing providers.
 */
interface TicketingConnectorInterface {
	/**
	 * Returns a list of tickets for current user context.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_tickets_for_user( int $user_id ): array;
}
