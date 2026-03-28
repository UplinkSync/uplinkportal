<?php
/**
 * Service Catalog UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Services\ServiceDefinitionStore;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Service Catalog admin page.
 *
 * Layout order:
 *   1. Services — line-item service cards from enabled modules
 *   2. Plans    — accordion rows with configurable plan icons
 */
class ServiceCatalogUi {

	/**
	 * Available plan icon keys mapped to dashicons classes.
	 *
	 * @var array<string,string>
	 */
	private const PLAN_ICON_MAP = array(
		'portfolio'     => 'dashicons-portfolio',
		'chart-line'    => 'dashicons-chart-line',
		'building'      => 'dashicons-building',
		'shield'        => 'dashicons-shield',
		'cloud'         => 'dashicons-cloud',
		'admin-generic' => 'dashicons-admin-generic',
		'star-filled'   => 'dashicons-star-filled',
		'awards'        => 'dashicons-awards',
	);

	/**
	 * Service definition store.
	 *
	 * @var ServiceDefinitionStore
	 */
	private ServiceDefinitionStore $service_definition_store;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Constructs the Service Catalog renderer.
	 *
	 * @param ServiceDefinitionStore $service_definition_store Service definitions per module.
	 * @param ModuleManager          $module_manager           Module registry.
	 */
	public function __construct( ServiceDefinitionStore $service_definition_store, ModuleManager $module_manager ) {
		$this->service_definition_store = $service_definition_store;
		$this->module_manager           = $module_manager;
	}

	/**
	 * Renders the full Service Catalog page body (inside the .wrap container).
	 *
	 * @param array<string,array<string,mixed>> $plan_templates Plan template data from wp_options.
	 * @param int                               $saved          1 if catalog was just saved.
	 * @param string                            $modules_url    URL to Configuration > Modules tab.
	 * @return void
	 */
	public function render( array $plan_templates, int $saved, string $modules_url ): void {
		?>
		<section class="uplinkportal-admin-hero">
			<div>
				<h1><?php echo esc_html__( 'Service Catalog', 'uplinkportal' ); ?></h1>
				<p><?php echo esc_html__( 'Manage your service offerings and plan configurations from a single workspace.', 'uplinkportal' ); ?></p>
			</div>
			<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Core Functionality', 'uplinkportal' ); ?></span>
		</section>

		<?php if ( 1 === $saved ) : ?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Service catalog updated.', 'uplinkportal' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="uplinkportal-admin-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-uplinkportal-plan-form>
				<?php wp_nonce_field( 'uplinkportal_save_plan_templates', 'uplinkportal_plan_templates_nonce' ); ?>
				<input type="hidden" name="action" value="uplinkportal_save_plan_templates" />

				<?php $this->render_services_section( $modules_url ); ?>
				<?php $this->render_plans_section( $plan_templates ); ?>

				<?php submit_button( __( 'Save Plan Templates', 'uplinkportal' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the Services section — a grid of service cards from enabled modules.
	 *
	 * Services are now listed FIRST, before Plans.
	 *
	 * @param string $modules_url URL to Configuration > Modules tab (for cogwheel link).
	 * @return void
	 */
	private function render_services_section( string $modules_url ): void {
		$enabled_modules = $this->module_manager->enabled();
		$enabled_slugs   = array_map( fn( $m ) => $m->id(), $enabled_modules );
		$module_labels   = array();
		foreach ( $enabled_modules as $module ) {
			$module_labels[ $module->id() ] = $module->label();
		}

		$all_services = $this->service_definition_store->get_all_enabled_services( $enabled_slugs );
		?>
		<section class="uplinkportal-service-section">
			<h2><?php echo esc_html__( 'Services', 'uplinkportal' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Line-item services available in your catalog, grouped by module. Use the cogwheel to configure module settings. Services appear automatically when modules are enabled.', 'uplinkportal' ); ?>
			</p>

			<?php if ( empty( $all_services ) ) : ?>
				<p class="description">
					<?php echo esc_html__( 'No active services found. Enable modules under Configuration > Modules to add services to your catalog.', 'uplinkportal' ); ?>
					<a href="<?php echo esc_url( $modules_url ); ?>"><?php echo esc_html__( 'Configure Modules', 'uplinkportal' ); ?></a>
				</p>
			<?php else : ?>
				<div class="uplinkportal-service-card-grid">
					<?php foreach ( $all_services as $service ) : ?>
						<?php
						$service_slug = isset( $service['slug'] ) ? (string) $service['slug'] : '';
						$service_name = isset( $service['name'] ) ? (string) $service['name'] : '';
						$service_desc = isset( $service['description'] ) ? (string) $service['description'] : '';
						$service_icon = isset( $service['icon'] ) ? (string) $service['icon'] : 'dashicons-admin-generic';
						$module_slug  = isset( $service['module_slug'] ) ? (string) $service['module_slug'] : '';
						$module_label = isset( $module_labels[ $module_slug ] ) ? (string) $module_labels[ $module_slug ] : $module_slug;
						$cog_url      = add_query_arg(
							array(
								'page' => 'uplinkportal-settings',
								'tab'  => 'modules',
							),
							admin_url( 'admin.php' )
						);
						?>
						<article class="uplinkportal-service-card">
							<div class="uplinkportal-service-card-header">
								<div class="uplinkportal-service-card-icon">
									<span class="dashicons <?php echo esc_attr( $service_icon ); ?>" aria-hidden="true"></span>
								</div>
								<div>
									<h3 class="uplinkportal-service-card-title"><?php echo esc_html( $service_name ); ?></h3>
									<p class="uplinkportal-service-card-desc"><?php echo esc_html( $service_desc ); ?></p>
								</div>
							</div>
							<div class="uplinkportal-service-card-meta">
								<span class="uplinkportal-service-card-module-chip">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s module label. */
											__( 'Based from: %s', 'uplinkportal' ),
											$module_label
										)
									);
									?>
								</span>
							</div>
							<div class="uplinkportal-service-card-actions">
								<span class="uplinkportal-status-pill is-enabled">
									<?php echo esc_html__( 'Active', 'uplinkportal' ); ?>
								</span>
								<a
									href="<?php echo esc_url( $cog_url ); ?>"
									class="uplinkportal-service-card-cog"
									title="<?php echo esc_attr__( 'Configure module settings', 'uplinkportal' ); ?>"
									aria-label="<?php echo esc_attr__( 'Configure module settings', 'uplinkportal' ); ?>"
								>
									<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
								</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Renders the Plans section as an accordion — previously shown first with modals.
	 *
	 * Plans are now listed SECOND, after Services, and use an inline accordion
	 * instead of modal dialogs for a more integrated, scannable layout.
	 *
	 * @param array<string,array<string,mixed>> $plan_templates Plan template data.
	 * @return void
	 */
	private function render_plans_section( array $plan_templates ): void {
		$module_labels = array();
		foreach ( $this->module_manager->all() as $module ) {
			$module_labels[ $module->id() ] = $module->label();
		}
		$enabled_module_ids = array_map( fn( $m ) => $m->id(), $this->module_manager->enabled() );
		?>
		<section class="uplinkportal-service-section">
			<h2><?php echo esc_html__( 'Plans', 'uplinkportal' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Plan templates define default module access for your clients. Expand a plan to configure its icon, description, and included services.', 'uplinkportal' ); ?>
			</p>

			<div class="uplinkportal-accordion" data-uplinkportal-plan-accordion>
				<?php foreach ( $plan_templates as $plan_key => $plan_data ) : ?>
					<?php
					$plan_key         = sanitize_key( (string) $plan_key );
					$plan_label       = isset( $plan_data['label'] ) ? sanitize_text_field( (string) $plan_data['label'] ) : '';
					$plan_description = isset( $plan_data['description'] ) ? sanitize_text_field( (string) $plan_data['description'] ) : '';
					$plan_icon        = isset( $plan_data['icon'] ) ? sanitize_key( (string) $plan_data['icon'] ) : 'portfolio';
					$plan_modules     = isset( $plan_data['modules'] ) && is_array( $plan_data['modules'] )
						? array_values( array_map( 'sanitize_key', $plan_data['modules'] ) )
						: array();
					$icon_class       = isset( self::PLAN_ICON_MAP[ $plan_icon ] ) ? self::PLAN_ICON_MAP[ $plan_icon ] : 'dashicons-portfolio';
					$module_count     = count( $plan_modules );
					$panel_id         = 'uplinkportal-plan-panel-' . $plan_key;
					?>
					<div class="uplinkportal-accordion-item" data-plan-shell="<?php echo esc_attr( $plan_key ); ?>">
						<input type="hidden" name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][key]" value="<?php echo esc_attr( $plan_key ); ?>" />

						<button
							type="button"
							class="uplinkportal-accordion-trigger"
							data-uplinkportal-accordion-trigger
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						>
							<div class="uplinkportal-accordion-trigger-icon" data-plan-icon-preview>
								<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
							</div>
							<span data-plan-title-preview><?php echo esc_html( '' !== $plan_label ? $plan_label : __( 'Untitled Plan', 'uplinkportal' ) ); ?></span>
							<span class="uplinkportal-status-pill is-enabled" style="margin-left: auto; margin-right: 8px;">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d number of included services. */
										_n( '%d service', '%d services', $module_count, 'uplinkportal' ),
										$module_count
									)
								);
								?>
							</span>
							<span class="uplinkportal-accordion-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
						</button>

						<div id="<?php echo esc_attr( $panel_id ); ?>" class="uplinkportal-accordion-panel" hidden>
							<label>
								<?php echo esc_html__( 'Plan Key', 'uplinkportal' ); ?>
								<input type="text" class="regular-text" value="<?php echo esc_attr( $plan_key ); ?>" readonly />
							</label>

							<label>
								<?php echo esc_html__( 'Title', 'uplinkportal' ); ?>
								<input
									type="text"
									class="regular-text"
									name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][label]"
									value="<?php echo esc_attr( $plan_label ); ?>"
									data-plan-title-field
								/>
							</label>

							<label>
								<?php echo esc_html__( 'Short Description', 'uplinkportal' ); ?>
								<textarea
									rows="2"
									class="large-text"
									name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][description]"
									data-plan-description-field
								><?php echo esc_textarea( $plan_description ); ?></textarea>
							</label>

							<label>
								<?php echo esc_html__( 'Plan Icon', 'uplinkportal' ); ?>
								<select name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][icon]" data-plan-icon-field>
									<?php foreach ( self::PLAN_ICON_MAP as $icon_key => $icon_dashicon ) : ?>
										<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $icon_key, $plan_icon ); ?>>
											<?php echo esc_html( ucwords( str_replace( '-', ' ', $icon_key ) ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>

							<fieldset>
								<legend><?php echo esc_html__( 'Included Services', 'uplinkportal' ); ?></legend>
								<?php foreach ( $module_labels as $module_key => $module_label ) : ?>
									<label class="uplinkportal-plan-module-check">
										<input
											type="checkbox"
											name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][modules][<?php echo esc_attr( $module_key ); ?>]"
											value="1"
											data-plan-module-checkbox
											<?php checked( in_array( $module_key, $plan_modules, true ) ); ?>
											<?php disabled( ! in_array( $module_key, $enabled_module_ids, true ) ); ?>
										/>
										<?php echo esc_html( (string) $module_label ); ?>
										<?php if ( ! in_array( $module_key, $enabled_module_ids, true ) ) : ?>
											<em class="description"><?php echo esc_html__( '(disabled in Modules)', 'uplinkportal' ); ?></em>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html__( 'Modules disabled in Configuration > Modules are locked here.', 'uplinkportal' ); ?></p>
							</fieldset>

							<p>
								<label>
									<input
										type="checkbox"
										name="plan_templates[<?php echo esc_attr( $plan_key ); ?>][remove]"
										value="1"
										data-plan-remove-checkbox
									/>
									<?php echo esc_html__( 'Remove this plan when saving', 'uplinkportal' ); ?>
								</label>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div style="margin-top: 12px;">
				<button
					type="button"
					class="button button-secondary uplinkportal-plan-add-card"
					data-uplinkportal-add-plan-card
					aria-label="<?php echo esc_attr__( 'Create a new plan', 'uplinkportal' ); ?>"
				>
					<span class="dashicons dashicons-plus" aria-hidden="true"></span>
					<?php echo esc_html__( 'Create Plan', 'uplinkportal' ); ?>
				</button>
			</div>
		</section>
		<?php
	}
}
