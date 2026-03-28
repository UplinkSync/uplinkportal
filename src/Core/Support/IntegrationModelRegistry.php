<?php
/**
 * Canonical integration model registry.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides canonical model metadata for integration and compliance workflows.
 */
final class IntegrationModelRegistry {
	/**
	 * Finance model id.
	 *
	 * @var string
	 */
	public const MODEL_FINANCE = 'finance';

	/**
	 * IT support model id.
	 *
	 * @var string
	 */
	public const MODEL_IT_SUPPORT = 'it_support';

	/**
	 * Projects model id.
	 *
	 * @var string
	 */
	public const MODEL_PROJECTS = 'projects';

	/**
	 * Licensing model id.
	 *
	 * @var string
	 */
	public const MODEL_LICENSING = 'licensing';

	/**
	 * Documents model id.
	 *
	 * @var string
	 */
	public const MODEL_DOCUMENTS = 'documents';

	/**
	 * Returns canonical model definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function all(): array {
		return array(
			self::MODEL_FINANCE    => array(
				'module_key'         => 'finance',
				'validation_section' => 'billing',
				'classification'     => 'confidential',
			),
			self::MODEL_IT_SUPPORT => array(
				'module_key'         => 'it_support',
				'validation_section' => 'helpdesk',
				'classification'     => 'confidential',
			),
			self::MODEL_PROJECTS   => array(
				'module_key'         => 'projects',
				'validation_section' => 'pm',
				'classification'     => 'internal',
			),
			self::MODEL_LICENSING  => array(
				'module_key'         => 'licensing',
				'validation_section' => '',
				'classification'     => 'internal',
			),
			self::MODEL_DOCUMENTS  => array(
				'module_key'         => 'documents',
				'validation_section' => '',
				'classification'     => 'regulated',
			),
		);
	}

	/**
	 * Returns all model ids.
	 *
	 * @return array<int,string>
	 */
	public static function ids(): array {
		return array_keys( self::all() );
	}

	/**
	 * Returns whether model id is registered.
	 *
	 * @param string $model_id Model id.
	 * @return bool
	 */
	public static function has( string $model_id ): bool {
		return array_key_exists( sanitize_key( $model_id ), self::all() );
	}

	/**
	 * Returns model id from validation section.
	 *
	 * @param string $section Validation section key.
	 * @return string
	 */
	public static function model_for_validation_section( string $section ): string {
		$section = sanitize_key( $section );
		foreach ( self::all() as $model_id => $model ) {
			if ( isset( $model['validation_section'] ) && sanitize_key( (string) $model['validation_section'] ) === $section ) {
				return $model_id;
			}
		}

		return '';
	}

	/**
	 * Returns validation section for one model id.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	public static function validation_section_for_model( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		$models   = self::all();

		if ( ! isset( $models[ $model_id ]['validation_section'] ) ) {
			return '';
		}

		return sanitize_key( (string) $models[ $model_id ]['validation_section'] );
	}

	/**
	 * Returns model id for one module key.
	 *
	 * @param string $module_key Module key.
	 * @return string
	 */
	public static function model_for_module( string $module_key ): string {
		$module_key = sanitize_key( $module_key );
		foreach ( self::all() as $model_id => $model ) {
			if ( isset( $model['module_key'] ) && sanitize_key( (string) $model['module_key'] ) === $module_key ) {
				return $model_id;
			}
		}

		return '';
	}
}
