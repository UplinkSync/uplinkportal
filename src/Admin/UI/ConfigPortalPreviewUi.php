<?php
/**
 * Configuration > Portal Preview UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

use UplinkPortal\Core\Support\PortalCustomizationStore;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Configuration > Portal Preview tab using an iframe approach.
 *
 * The preview loads the client portal in a sandboxed iframe, with device-frame
 * switcher buttons for desktop, tablet, and mobile widths. Configuration fields
 * (brand color, welcome message, footer note) remain in the right panel and
 * update the live preview via JS.
 */
class ConfigPortalPreviewUi {

	/**
	 * Portal customization store.
	 *
	 * @var PortalCustomizationStore
	 */
	private PortalCustomizationStore $portal_customization_store;

	/**
	 * Constructs the Portal Preview renderer.
	 *
	 * @param PortalCustomizationStore $portal_customization_store Customization storage.
	 */
	public function __construct( PortalCustomizationStore $portal_customization_store ) {
		$this->portal_customization_store = $portal_customization_store;
	}

	/**
	 * Renders the Portal Preview tab body.
	 *
	 * @param array<string,string> $defaults     Customization defaults (brand_primary_color, welcome_message, footer_note).
	 * @param string               $preview_url  Nonce-protected preview URL that renders the portal shell.
	 * @param string               $status       Status key from query arg (e.g. 'saved_portal_configuration').
	 * @param array<string,string> $portal_roles Role slug => label map for the role-view selector.
	 * @return void
	 */
	public function render( array $defaults, string $preview_url, string $status, array $portal_roles = array() ): void {
		?>
		<h2><?php echo esc_html__( 'Portal Preview', 'uplinkportal' ); ?></h2>
		<p>
			<?php echo esc_html__( 'Preview how your client portal looks before saving. Adjust settings on the right and click "Save Portal Configuration" to apply.', 'uplinkportal' ); ?>
		</p>

		<?php if ( 'saved_portal_configuration' === $status ) : ?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Portal configuration saved.', 'uplinkportal' ); ?></p>
			</div>
		<?php elseif ( 'error_portal_storage' === $status ) : ?>
			<div class="notice notice-error inline">
				<p><?php echo esc_html__( 'Portal configuration could not be saved. Verify filesystem permissions and try again.', 'uplinkportal' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="uplinkportal-portal-preview-layout" data-uplinkportal-portal-preview>
			<!-- Left: iframe preview with device switcher -->
			<div class="uplinkportal-portal-preview-iframe-area">
				<?php if ( ! empty( $portal_roles ) ) : ?>
				<div class="uplinkportal-preview-role-bar">
					<label for="uplinkportal-preview-role">
						<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
						<span><?php echo esc_html__( 'Role view', 'uplinkportal' ); ?></span>
					</label>
					<select
						id="uplinkportal-preview-role"
						data-uplinkportal-preview-role
						data-preview-base-url="<?php echo esc_attr( $preview_url ); ?>"
					>
						<option value=""><?php echo esc_html__( '— Default view —', 'uplinkportal' ); ?></option>
						<?php foreach ( $portal_roles as $role_slug => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>"><?php echo esc_html( $role_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
					<span class="uplinkportal-preview-mode-badge">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						<?php echo esc_html__( 'Preview Mode', 'uplinkportal' ); ?>
					</span>

					<nav class="uplinkportal-preview-frame-switcher" aria-label="<?php echo esc_attr__( 'Device preview', 'uplinkportal' ); ?>">
						<button type="button" class="uplinkportal-preview-device-btn is-active" data-uplinkportal-preview-device="desktop">
							<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
							<?php echo esc_html__( 'Desktop', 'uplinkportal' ); ?>
						</button>
						<button type="button" class="uplinkportal-preview-device-btn" data-uplinkportal-preview-device="tablet">
							<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
							<?php echo esc_html__( 'Tablet', 'uplinkportal' ); ?>
						</button>
						<button type="button" class="uplinkportal-preview-device-btn" data-uplinkportal-preview-device="mobile">
							<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
							<?php echo esc_html__( 'Mobile', 'uplinkportal' ); ?>
						</button>
					</nav>
				</div>

				<div class="uplinkportal-preview-shell uplinkportal-preview-shell--desktop" data-uplinkportal-preview-shell>
					<iframe
						src="<?php echo esc_url( $preview_url ); ?>"
						title="<?php echo esc_attr__( 'Client portal preview', 'uplinkportal' ); ?>"
						sandbox="allow-same-origin allow-scripts"
						data-uplinkportal-preview-iframe
					></iframe>
				</div>

				<p class="description" style="margin-top: 8px;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s private directory path. */
							__( 'Configuration is stored privately at: %s', 'uplinkportal' ),
							$this->portal_customization_store->get_storage_dir()
						)
					);
					?>
				</p>
			</div>

			<!-- Right: configuration form -->
			<section class="uplinkportal-customization-section uplinkportal-customization-section--portal-config">
				<div class="uplinkportal-section-head">
					<h3><?php echo esc_html__( 'Portal Configuration', 'uplinkportal' ); ?></h3>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-uplinkportal-portal-config-form>
					<?php wp_nonce_field( 'uplinkportal_save_customization_defaults', 'uplinkportal_customization_nonce' ); ?>
					<input type="hidden" name="action" value="uplinkportal_save_customization_defaults" />
					<input type="hidden" name="customization_section" value="portal_configuration" />

					<div class="uplinkportal-customization-grid">
						<label>
							<span><?php echo esc_html__( 'Brand Primary Color', 'uplinkportal' ); ?></span>
							<input
								type="color"
								name="portal_configuration[brand_primary_color]"
								value="<?php echo esc_attr( (string) $defaults['brand_primary_color'] ); ?>"
								data-uplinkportal-preview-color
							/>
						</label>

						<label>
							<span><?php echo esc_html__( 'Welcome Message', 'uplinkportal' ); ?></span>
							<input
								type="text"
								class="regular-text"
								name="portal_configuration[portal_welcome_message]"
								value="<?php echo esc_attr( (string) $defaults['portal_welcome_message'] ); ?>"
							/>
						</label>

						<label>
							<span><?php echo esc_html__( 'Portal Footer Note', 'uplinkportal' ); ?></span>
							<textarea
								class="large-text"
								rows="3"
								name="portal_configuration[portal_footer_note]"
							><?php echo esc_textarea( (string) $defaults['portal_footer_note'] ); ?></textarea>
						</label>
					</div>

					<?php submit_button( __( 'Save Portal Configuration', 'uplinkportal' ) ); ?>
				</form>
			</section>
		</div>
		<?php
	}
}
