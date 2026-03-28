<?php
/**
 * Tenant boundary scope helpers.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Applies tenant-boundary scope rules for multisite/user operations.
 */
final class TenantBoundaryScope {
	/**
	 * Returns user query args with tenant scope constraints.
	 *
	 * @param array<string,mixed> $args Existing args.
	 * @param Options             $options Options helper.
	 * @return array<string,mixed>
	 */
	public static function apply_user_query_scope( array $args, Options $options ): array {
		if ( ! is_multisite() ) {
			return $args;
		}

		if ( ! $options->is_tenant_site_scope_enforced() ) {
			return $args;
		}

		$mode = $options->get_tenant_boundary_mode();
		if ( 'multisite_network_shared' === $mode ) {
			if ( $options->requires_super_admin_for_network_shared() && ! is_super_admin() ) {
				$args['blog_id'] = get_current_blog_id();
				return $args;
			}

			$args['blog_id'] = 0;
			return $args;
		}

		$args['blog_id'] = get_current_blog_id();
		return $args;
	}

	/**
	 * Returns whether one user is in current tenant scope.
	 *
	 * @param int     $user_id User id.
	 * @param Options $options Options helper.
	 * @return bool
	 */
	public static function is_user_in_scope( int $user_id, Options $options ): bool {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( ! is_multisite() || ! $options->is_tenant_site_scope_enforced() ) {
			return true;
		}

		$mode = $options->get_tenant_boundary_mode();
		if ( 'multisite_network_shared' === $mode ) {
			if ( $options->requires_super_admin_for_network_shared() ) {
				return is_super_admin();
			}

			return true;
		}

		return is_user_member_of_blog( $user_id, get_current_blog_id() );
	}
}
