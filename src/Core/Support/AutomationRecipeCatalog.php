<?php
/**
 * Automation recipe catalog.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides baseline no-code automation recipe templates.
 */
class AutomationRecipeCatalog {
	/**
	 * Returns recipe templates keyed by recipe id.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function templates(): array {
		return array(
			'finance_invoice_created_notify_workspace' => array(
				'model'             => IntegrationModelRegistry::MODEL_FINANCE,
				'label'             => __( 'Invoice Created -> Notify Client Workspace', 'uplinkportal' ),
				'description'       => __( 'Sends a client workspace notification when a new invoice is created.', 'uplinkportal' ),
				'trigger'           => 'invoice_created',
				'action'            => 'notify_client_workspace',
				'compliance_labels' => array( 'gdpr', 'msp_default' ),
			),
			'it_support_ticket_escalated_trigger_sla'  => array(
				'model'             => IntegrationModelRegistry::MODEL_IT_SUPPORT,
				'label'             => __( 'Ticket Escalated -> Trigger SLA Workflow', 'uplinkportal' ),
				'description'       => __( 'Triggers the configured SLA escalation workflow when a ticket is escalated.', 'uplinkportal' ),
				'trigger'           => 'ticket_escalated',
				'action'            => 'trigger_sla_workflow',
				'compliance_labels' => array( 'gdpr', 'hipaa', 'msp_default' ),
			),
			'projects_milestone_complete_push_portal'  => array(
				'model'             => IntegrationModelRegistry::MODEL_PROJECTS,
				'label'             => __( 'Milestone Complete -> Push Portal Update', 'uplinkportal' ),
				'description'       => __( 'Publishes project milestone updates to the client portal timeline.', 'uplinkportal' ),
				'trigger'           => 'milestone_completed',
				'action'            => 'push_portal_update',
				'compliance_labels' => array( 'gdpr', 'msp_default' ),
			),
			'documents_agreement_uploaded_notify_and_retain' => array(
				'model'             => IntegrationModelRegistry::MODEL_DOCUMENTS,
				'label'             => __( 'Agreement Uploaded -> Notify Client + Start Retention Timer', 'uplinkportal' ),
				'description'       => __( 'Notifies the client about newly uploaded agreements and applies retention policy timers.', 'uplinkportal' ),
				'trigger'           => 'agreement_uploaded',
				'action'            => 'notify_and_start_retention_timer',
				'compliance_labels' => array( 'gdpr', 'hipaa', 'msp_default' ),
			),
		);
	}

	/**
	 * Returns template ids.
	 *
	 * @return array<int,string>
	 */
	public static function ids(): array {
		return array_keys( self::templates() );
	}

	/**
	 * Returns whether recipe id exists.
	 *
	 * @param string $recipe_id Recipe id.
	 * @return bool
	 */
	public static function has_template( string $recipe_id ): bool {
		return array_key_exists( sanitize_key( $recipe_id ), self::templates() );
	}
}
