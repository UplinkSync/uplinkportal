<?php
/**
 * Billing connector contract.
 *
 * @package UplinkPortal\Core\Integrations
 */

namespace UplinkPortal\Core\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Connector contract for billing providers.
 */
interface BillingConnectorInterface {
	/**
	 * Returns billing entries for current user context.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_invoices_for_user( int $user_id ): array;
}
