<?php
/**
 * Automation recipe runtime entitlement gate.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Central runtime entrypoint for recipe execution with entitlement enforcement.
 */
class AutomationRecipeRuntimeGate {
	/**
	 * Action hook used to run one recipe.
	 *
	 * @var string
	 */
	public const EXECUTION_HOOK = 'uplinkportal_run_automation_recipe';

	/**
	 * Options helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Entitlement enforcer.
	 *
	 * @var EntitlementEnforcer
	 */
	private EntitlementEnforcer $entitlement_enforcer;

	/**
	 * Constructor.
	 *
	 * @param Options             $options Options helper.
	 * @param EntitlementEnforcer $entitlement_enforcer Entitlement enforcer.
	 */
	public function __construct( Options $options, EntitlementEnforcer $entitlement_enforcer ) {
		$this->options              = $options;
		$this->entitlement_enforcer = $entitlement_enforcer;
	}

	/**
	 * Registers runtime execution hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::EXECUTION_HOOK, array( $this, 'handle_recipe_execution' ), 10, 3 );
	}

	/**
	 * Handles runtime recipe execution with entitlement gate.
	 *
	 * @param int                 $client_id Client id.
	 * @param string              $recipe_id Recipe id.
	 * @param array<string,mixed> $payload Runtime payload.
	 * @return void
	 */
	public function handle_recipe_execution( int $client_id, string $recipe_id, array $payload = array() ): void {
		$client_id = absint( $client_id );
		$recipe_id = sanitize_key( $recipe_id );

		if ( $client_id <= 0 || '' === $recipe_id || ! AutomationRecipeCatalog::has_template( $recipe_id ) ) {
			return;
		}

		$templates = AutomationRecipeCatalog::templates();
		$template  = isset( $templates[ $recipe_id ] ) && is_array( $templates[ $recipe_id ] ) ? $templates[ $recipe_id ] : array();
		$model_id  = isset( $template['model'] ) ? sanitize_key( (string) $template['model'] ) : '';
		if ( '' === $model_id ) {
			return;
		}

		$profile     = $this->options->get_integration_profile( $model_id );
		$provider    = isset( $profile['provider'] ) ? sanitize_key( (string) $profile['provider'] ) : 'custom';
		$module_slug = $this->module_slug_for_model( $model_id );

		$gate = $this->entitlement_enforcer->enforce_execution_gate(
			$client_id,
			$module_slug,
			'' !== $provider ? $provider : 'custom',
			$recipe_id
		);

		if ( is_wp_error( $gate ) ) {
			/**
			 * Fires when recipe execution is denied by runtime entitlement gate.
			 *
			 * @param int      $client_id Client id.
			 * @param string   $recipe_id Recipe id.
			 * @param string   $model_id Model id.
			 * @param string   $provider Provider id.
			 * @param WP_Error $gate Gate error.
			 * @param array    $payload Runtime payload.
			 */
			do_action( 'uplinkportal_recipe_execution_denied', $client_id, $recipe_id, $model_id, $provider, $gate, $payload );
			return;
		}

		/**
		 * Fires when a recipe passes runtime entitlement checks and is ready to execute.
		 *
		 * Final execution is delegated to backend/add-on listeners.
		 *
		 * @param int                $client_id Client id.
		 * @param string             $recipe_id Recipe id.
		 * @param string             $model_id Model id.
		 * @param string             $provider Provider id.
		 * @param array<string,mixed> $payload Runtime payload.
		 */
		do_action( 'uplinkportal_execute_automation_recipe', $client_id, $recipe_id, $model_id, $provider, $payload );
	}

	/**
	 * Maps model id to module slug used for entitlement checks.
	 *
	 * @param string $model_id Model id.
	 * @return string
	 */
	private function module_slug_for_model( string $model_id ): string {
		$model_id = sanitize_key( $model_id );
		if ( IntegrationModelRegistry::MODEL_DOCUMENTS === $model_id || IntegrationModelRegistry::MODEL_LICENSING === $model_id ) {
			return 'database';
		}

		return $model_id;
	}
}
