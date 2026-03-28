<?php
/**
 * Configuration > Modules UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Configuration > Modules tab with a three-section card layout.
 *
 * Three sections rendered in order:
 *   1. Core Modules     — always-available modules bundled with the plugin
 *   2. Add-on Catalog   — Pro boundary placeholder cards (available from UplinkPortal Pro)
 *   3. Custom Modules   — MSP-uploaded modules registered via filter
 *
 * Card styling matches the Service Catalog services section for visual consistency.
 * Existing JS attributes (data-module-key, data-module-config-trigger, etc.) are
 * preserved so admin-modules.js and admin-backend.js continue to work without changes.
 */
class ConfigModulesUi {

	use ProPlaceholderTrait;

	/**
	 * Core module slugs that are always present in this section.
	 *
	 * @var array<int,string>
	 */
	private const CORE_MODULE_SLUGS = array( 'finance', 'it_support', 'projects' );

	/**
	 * Icon map for core modules.
	 *
	 * @var array<string,string>
	 */
	private const CORE_MODULE_ICONS = array(
		'finance'    => 'dashicons-money-alt',
		'it_support' => 'dashicons-sos',
		'projects'   => 'dashicons-chart-bar',
	);

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Constructs the Modules tab renderer.
	 *
	 * @param ModuleManager $module_manager Module registry.
	 */
	public function __construct( ModuleManager $module_manager ) {
		$this->module_manager = $module_manager;
	}

	/**
	 * Renders the Configuration > Modules tab body.
	 *
	 * This method is called within the tab dispatcher in SettingsPage and
	 * should not render its own form tag — the tab form is managed by SettingsPage.
	 *
	 * @param array<string,bool> $enabled_states  Module key => enabled state from options.
	 * @param string             $saved_status     Status from query arg ('saved_modules' etc.).
	 * @param string             $options_key      WordPress option key used for checkbox field names.
	 * @return void
	 */
	public function render( array $enabled_states, string $saved_status, string $options_key = '' ): void {
		if ( 'saved_modules' === $saved_status ) {
			?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Module settings saved.', 'uplinkportal' ); ?></p>
			</div>
			<?php
		}

		$all_modules        = $this->module_manager->all();
		$enabled_module_ids = array_map( fn( ModuleInterface $m ) => $m->id(), $this->module_manager->enabled() );

		// Partition modules into core vs. custom.
		$core_modules   = array();
		$custom_modules = array();
		foreach ( $all_modules as $module ) {
			if ( in_array( $module->id(), self::CORE_MODULE_SLUGS, true ) ) {
				$core_modules[] = $module;
			} else {
				$custom_modules[] = $module;
			}
		}

		// Section order: Core → Custom → Add-on Catalog (pro boundary placeholder).
		$this->render_core_modules_section( $core_modules, $enabled_module_ids, $enabled_states, $options_key );
		$this->render_custom_modules_section( $custom_modules, $enabled_module_ids, $options_key );
		$this->render_addon_placeholder_section();
	}

	/**
	 * Renders the Core Modules section.
	 *
	 * @param array<int,ModuleInterface> $core_modules       Core module instances.
	 * @param array<int,string>          $enabled_module_ids IDs of currently-enabled modules.
	 * @param array<string,bool>         $enabled_states     Module key => enabled state from settings.
	 * @param string                     $options_key        WordPress option key for checkbox names.
	 * @return void
	 */
	private function render_core_modules_section( array $core_modules, array $enabled_module_ids, array $enabled_states, string $options_key ): void {
		?>
		<section class="uplinkportal-modules-section">
			<h3><?php echo esc_html__( 'Core Modules', 'uplinkportal' ); ?></h3>
			<p class="description">
				<?php echo esc_html__( 'Modules bundled with UplinkPortal. Toggle to enable or disable. Each module adds services to your Service Catalog and surfaces data in the portal.', 'uplinkportal' ); ?>
			</p>
			<div class="uplinkportal-service-card-grid">
				<?php foreach ( $core_modules as $module ) : ?>
					<?php $this->render_core_module_card( $module, $enabled_module_ids, $options_key ); ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Renders a single core module card.
	 *
	 * @param ModuleInterface   $module             Module instance.
	 * @param array<int,string> $enabled_module_ids IDs of currently-enabled modules.
	 * @param string            $options_key        WordPress option key for checkbox names.
	 * @return void
	 */
	private function render_core_module_card( ModuleInterface $module, array $enabled_module_ids, string $options_key ): void {
		$module_key  = $module->id();
		$module_icon = isset( self::CORE_MODULE_ICONS[ $module_key ] ) ? self::CORE_MODULE_ICONS[ $module_key ] : 'dashicons-screenoptions';
		$is_enabled  = in_array( $module_key, $enabled_module_ids, true );
		$is_required = false;
		$modal_id    = 'uplinkportal-module-config-modal-' . $module_key;
		?>
		<article class="uplinkportal-service-card" data-module-card="<?php echo esc_attr( $module_key ); ?>">
			<div class="uplinkportal-service-card-header">
				<div class="uplinkportal-service-card-icon">
					<span class="dashicons <?php echo esc_attr( $module_icon ); ?>" aria-hidden="true"></span>
				</div>
				<div>
					<h3 class="uplinkportal-service-card-title"><?php echo esc_html( $module->label() ); ?></h3>
					<p class="uplinkportal-service-card-desc">
						<?php echo esc_html__( 'Core module included with UplinkPortal.', 'uplinkportal' ); ?>
					</p>
				</div>
			</div>
			<div class="uplinkportal-service-card-meta">
				<span class="uplinkportal-service-card-module-chip">
					<?php echo esc_html__( 'Core', 'uplinkportal' ); ?>
				</span>
				<span class="uplinkportal-status-pill <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>">
					<?php echo esc_html( $is_enabled ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' ) ); ?>
				</span>
			</div>
			<?php
			// translators: %s is the name of the module being toggled.
			$label_enable = sprintf( __( 'Enable %s', 'uplinkportal' ), $module->label() );
			// translators: %s is the name of the module being configured.
			$label_configure = sprintf( __( 'Configure %s', 'uplinkportal' ), $module->label() );
			?>
			<div class="uplinkportal-service-card-actions">
				<label class="uplinkportal-toggle" title="<?php echo esc_attr( $label_enable ); ?>">
					<input
						type="checkbox"
						class="uplinkportal-toggle-input uplinkportal-module-checkbox"
						data-module-key="<?php echo esc_attr( $module_key ); ?>"
						name="<?php echo esc_attr( $options_key ); ?>[service_modules][<?php echo esc_attr( $module_key ); ?>]"
						value="1"
						<?php checked( $is_enabled ); ?>
						<?php disabled( $is_required ); ?>
					/>
					<span class="uplinkportal-toggle-track" aria-hidden="true">
						<span class="uplinkportal-toggle-thumb"></span>
					</span>
				</label>

				<button
					type="button"
					class="button button-secondary"
					data-module-config-trigger="<?php echo esc_attr( $module_key ); ?>"
					data-uplinkportal-open-module-modal="<?php echo esc_attr( $modal_id ); ?>"
					<?php disabled( ! $is_enabled ); ?>
					aria-label="<?php echo esc_attr( $label_configure ); ?>"
				>
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<?php echo esc_html__( 'Configure', 'uplinkportal' ); ?>
				</button>
			</div>
		</article>
		<?php
	}

	/**
	 * Renders the Add-on Module Catalog section as Pro boundary placeholders.
	 *
	 * @return void
	 */
	private function render_addon_placeholder_section(): void {
		?>
		<section class="uplinkportal-modules-section" style="margin-top: var(--msp-space-5);">
			<h3><?php echo esc_html__( 'Add-on Module Catalog', 'uplinkportal' ); ?></h3>
			<p class="description">
				<?php echo esc_html__( 'Premium add-ons extend UplinkPortal with advanced capabilities. Available from UplinkPortal Pro.', 'uplinkportal' ); ?>
			</p>
			<div class="uplinkportal-service-card-grid">
				<?php $this->render_addon_placeholder_cards(); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Renders the Custom Modules section.
	 *
	 * @param array<int,ModuleInterface> $custom_modules     Custom module instances.
	 * @param array<int,string>          $enabled_module_ids IDs of currently-enabled modules.
	 * @param string                     $options_key        WordPress option key for checkbox names.
	 * @return void
	 */
	private function render_custom_modules_section( array $custom_modules, array $enabled_module_ids, string $options_key ): void {
		?>
		<section class="uplinkportal-modules-section" style="margin-top: var(--msp-space-5);">
			<h3><?php echo esc_html__( 'Custom Modules', 'uplinkportal' ); ?></h3>
			<p class="description">
				<?php echo esc_html__( 'MSP-managed custom modules registered via the uplinkportal_custom_modules filter. Custom modules are your responsibility — see CLAUDE.md for safety boundaries.', 'uplinkportal' ); ?>
			</p>
			<?php if ( empty( $custom_modules ) ) : ?>
				<p><?php echo esc_html__( 'No custom modules registered. Implement and register a module via the uplinkportal_custom_modules filter.', 'uplinkportal' ); ?></p>
			<?php else : ?>
				<div class="uplinkportal-service-card-grid">
					<?php foreach ( $custom_modules as $module ) : ?>
						<?php
						$module_key = $module->id();
						$is_enabled = in_array( $module_key, $enabled_module_ids, true );
						?>
						<article class="uplinkportal-service-card">
							<div class="uplinkportal-service-card-header">
								<div class="uplinkportal-service-card-icon">
									<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
								</div>
								<div>
									<h3 class="uplinkportal-service-card-title"><?php echo esc_html( $module->label() ); ?></h3>
									<p class="uplinkportal-service-card-desc">
										<?php echo esc_html__( 'Custom module registered by this MSP deployment.', 'uplinkportal' ); ?>
									</p>
								</div>
							</div>
							<div class="uplinkportal-service-card-meta">
								<span class="uplinkportal-service-card-module-chip">
									<?php echo esc_html__( 'Custom', 'uplinkportal' ); ?>
								</span>
								<span class="uplinkportal-status-pill <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>">
									<?php echo esc_html( $is_enabled ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' ) ); ?>
								</span>
							</div>
							<div class="uplinkportal-service-card-actions">
								<label class="uplinkportal-toggle">
									<input
										type="checkbox"
										class="uplinkportal-toggle-input uplinkportal-module-checkbox"
										data-module-key="<?php echo esc_attr( $module_key ); ?>"
										name="<?php echo esc_attr( $options_key ); ?>[service_modules][<?php echo esc_attr( $module_key ); ?>]"
										value="1"
										<?php checked( $is_enabled ); ?>
									/>
									<span class="uplinkportal-toggle-track" aria-hidden="true">
										<span class="uplinkportal-toggle-thumb"></span>
									</span>
								</label>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}
