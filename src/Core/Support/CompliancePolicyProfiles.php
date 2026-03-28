<?php
/**
 * Compliance policy bundle metadata.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides baseline policy bundle definitions.
 */
final class CompliancePolicyProfiles {
	/**
	 * Bundle key: MSP default.
	 *
	 * @var string
	 */
	public const BUNDLE_MSP_DEFAULT = 'msp_default';

	/**
	 * Bundle key: GDPR baseline.
	 *
	 * @var string
	 */
	public const BUNDLE_GDPR_BASELINE = 'gdpr_baseline';

	/**
	 * Bundle key: HIPAA baseline.
	 *
	 * @var string
	 */
	public const BUNDLE_HIPAA_BASELINE = 'hipaa_baseline';

	/**
	 * Returns policy bundles.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function bundles(): array {
		return array(
			self::BUNDLE_MSP_DEFAULT    => array(
				'label'       => __( 'MSP Default', 'uplinkportal' ),
				'description' => __( 'General managed-service baseline with practical default controls for most portal workloads.', 'uplinkportal' ),
				'controls'    => array(
					__( 'Role-gated infrastructure changes', 'uplinkportal' ),
					__( 'Security audit log visibility enabled', 'uplinkportal' ),
					__( 'Integration profile promotion workflow (draft/reviewed/active)', 'uplinkportal' ),
				),
			),
			self::BUNDLE_GDPR_BASELINE  => array(
				'label'       => __( 'GDPR Baseline', 'uplinkportal' ),
				'description' => __( 'EU privacy-oriented baseline emphasizing data minimization, retention control, and export/delete governance.', 'uplinkportal' ),
				'controls'    => array(
					__( 'Data minimization and purpose limitation review', 'uplinkportal' ),
					__( 'Subject access/export/deletion workflow alignment', 'uplinkportal' ),
					__( 'Retention and purge policy validation', 'uplinkportal' ),
				),
			),
			self::BUNDLE_HIPAA_BASELINE => array(
				'label'       => __( 'HIPAA Baseline', 'uplinkportal' ),
				'description' => __( 'Healthcare-oriented baseline emphasizing minimum necessary access, audit controls, and documented security safeguards.', 'uplinkportal' ),
				'controls'    => array(
					__( 'Minimum necessary access checks', 'uplinkportal' ),
					__( 'Enhanced auditability for access and export paths', 'uplinkportal' ),
					__( 'Transmission and storage security verification', 'uplinkportal' ),
				),
			),
		);
	}

	/**
	 * Returns whether bundle key exists.
	 *
	 * @param string $bundle_key Bundle key.
	 * @return bool
	 */
	public static function has_bundle( string $bundle_key ): bool {
		return array_key_exists( sanitize_key( $bundle_key ), self::bundles() );
	}
}
