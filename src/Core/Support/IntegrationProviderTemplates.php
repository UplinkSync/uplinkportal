<?php
/**
 * Integration provider templates.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides baseline low-code provider templates per integration model.
 */
final class IntegrationProviderTemplates {
	/**
	 * Returns provider templates grouped by model id.
	 *
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public static function all(): array {
			return array(
				IntegrationModelRegistry::MODEL_FINANCE    => array(
					'quickbooks_online' => self::template( 'QuickBooks Online', 'oauth2_pkce', array( 'customers.read', 'invoices.read', 'payments.read' ), 'billing_primary', '7y' ),
				),
				IntegrationModelRegistry::MODEL_IT_SUPPORT => array(
					'zendesk'                 => self::template( 'Zendesk', 'oauth2_pkce', array( 'tickets.read', 'tickets.write', 'users.read' ), 'helpdesk_primary', '3y' ),
					'freshdesk'               => self::template( 'Freshdesk', 'oauth2_pkce', array( 'tickets.read', 'tickets.write', 'contacts.read' ), 'helpdesk_primary', '3y' ),
					'jira_service_management' => self::template( 'Jira Service Management', 'oauth2_pkce', array( 'tickets.read', 'tickets.write', 'projects.read' ), 'helpdesk_primary', '3y' ),
				),
				IntegrationModelRegistry::MODEL_PROJECTS   => array(
					'asana'   => self::template( 'Asana', 'oauth2_pkce', array( 'projects.read', 'tasks.read', 'tasks.write' ), 'projects_primary', '3y' ),
					'monday'  => self::template( 'Monday.com', 'oauth2_pkce', array( 'boards.read', 'items.read', 'items.write' ), 'projects_primary', '3y' ),
					'clickup' => self::template( 'ClickUp', 'oauth2_pkce', array( 'spaces.read', 'tasks.read', 'tasks.write' ), 'projects_primary', '3y' ),
				),
				IntegrationModelRegistry::MODEL_LICENSING  => array(
					'cloudflare_worker' => self::template( 'Cloudflare Worker', 'signed_secret', array( 'license.validate', 'entitlements.sync' ), 'worker_primary', '2y' ),
				),
				IntegrationModelRegistry::MODEL_DOCUMENTS  => array(
					'private_backend_storage' => self::template( 'Private Backend Storage', 'bearer_token', array( 'documents.key.read', 'documents.upload', 'documents.stream' ), 'documents_primary', 'contract_based' ),
					's3_kms'                  => self::template( 'S3 + KMS', 'iam_role', array( 'objects.read', 'objects.write', 'keys.decrypt' ), 'documents_archive', 'contract_based' ),
				),
			);
	}

	/**
	 * Returns templates for one model id.
	 *
	 * @param string $model_id Model id.
	 * @return array<string,array<string,mixed>>
	 */
	public static function for_model( string $model_id ): array {
		$model_id = sanitize_key( $model_id );
		$all      = self::all();

		return isset( $all[ $model_id ] ) && is_array( $all[ $model_id ] )
			? $all[ $model_id ]
			: array();
	}

	/**
	 * Builds normalized template payload.
	 *
	 * @param string            $label Label.
	 * @param string            $auth_type Auth type.
	 * @param array<int,string> $scopes Scopes.
	 * @param string            $endpoint_alias Endpoint alias.
	 * @param string            $retention_policy Retention policy.
	 * @return array<string,mixed>
	 */
	private static function template( string $label, string $auth_type, array $scopes, string $endpoint_alias, string $retention_policy ): array {
		return array(
			'label'            => sanitize_text_field( $label ),
			'auth_type'        => sanitize_key( $auth_type ),
			'scopes'           => array_values( array_filter( array_map( 'sanitize_text_field', $scopes ) ) ),
			'endpoint_alias'   => sanitize_text_field( $endpoint_alias ),
			'retention_policy' => sanitize_key( $retention_policy ),
		);
	}
}
