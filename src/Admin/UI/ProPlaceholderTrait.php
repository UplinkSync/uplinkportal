<?php
/**
 * Pro placeholder card rendering trait.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Shared rendering of the Add-on Module Catalog placeholder cards.
 *
 * Used by UI classes that display the three Pro add-on placeholders
 * (Advanced Automation, Compliance Pack, Executive Insights).
 */
trait ProPlaceholderTrait {

	/**
	 * Returns the canonical Pro add-on placeholder definitions.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function addon_placeholder_definitions(): array {
		return array(
			array(
				'title'       => __( 'Advanced Automation', 'uplinkportal' ),
				'description' => __( 'Auto-route work, trigger escalations, and streamline routine operations.', 'uplinkportal' ),
				'icon'        => 'dashicons-admin-tools',
			),
			array(
				'title'       => __( 'Compliance Pack', 'uplinkportal' ),
				'description' => __( 'Expanded governance controls, retention policies, and compliance checks.', 'uplinkportal' ),
				'icon'        => 'dashicons-shield',
			),
			array(
				'title'       => __( 'Executive Insights', 'uplinkportal' ),
				'description' => __( 'Cross-module KPI rollups and trend snapshots for company leadership.', 'uplinkportal' ),
				'icon'        => 'dashicons-chart-line',
			),
		);
	}

	/**
	 * Renders the Pro placeholder article cards into the current output buffer.
	 *
	 * Callers are responsible for the surrounding <section> and <div> grid wrapper.
	 *
	 * @return void
	 */
	private function render_addon_placeholder_cards(): void {
		foreach ( $this->addon_placeholder_definitions() as $addon ) {
			$aria_label = sprintf(
				/* translators: %s: Add-on module name */
				esc_attr__( '%s (Pro add-on, not included)', 'uplinkportal' ),
				esc_attr( (string) $addon['title'] )
			);
			?>
			<article
				class="uplinkportal-pro-placeholder-card"
				aria-label="<?php echo $aria_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is double-escaped via esc_attr__ + esc_attr above ?>"
			>
				<header class="uplinkportal-pro-placeholder-card-header">
					<div class="uplinkportal-pro-placeholder-card-icon" aria-hidden="true">
						<span class="dashicons <?php echo esc_attr( (string) $addon['icon'] ); ?>"></span>
					</div>
					<span class="uplinkportal-pro-badge">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php echo esc_html__( 'Pro', 'uplinkportal' ); ?>
					</span>
				</header>
				<div class="uplinkportal-pro-placeholder-card-body">
					<h3 class="uplinkportal-service-card-title"><?php echo esc_html( (string) $addon['title'] ); ?></h3>
					<p class="uplinkportal-service-card-desc"><?php echo esc_html( (string) $addon['description'] ); ?></p>
				</div>
				<p class="uplinkportal-pro-placeholder-cta">
					<?php echo esc_html__( 'Available as a UplinkPortal Pro add-on.', 'uplinkportal' ); ?>
				</p>
			</article>
			<?php
		}
	}
}
