<?php
/**
 * Operations Dashboard UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Operations Dashboard with role-based KPI views.
 *
 * Users with `msp_manage` see a view-switcher that lets them browse all views.
 * Specialized MSP roles (executive, finance, technician) see their own view
 * directly without the switcher.
 *
 * All KPI values are placeholders; real data flows from connected integrations
 * via the `uplinkportal_dashboard_kpi_{view}` filter.
 */
class OperationsDashboardUi {

	/**
	 * Valid view identifiers.
	 *
	 * @var array<int,string>
	 */
	private const VALID_VIEWS = array( 'executive', 'finance', 'technician' );

	/**
	 * Renders the full Operations Dashboard body (inside the .wrap container).
	 *
	 * @param \WP_User    $user           Current WordPress user.
	 * @param string|null $requested_view View key from the `?view=` query arg ('executive'|'finance'|'technician'), or null.
	 * @return void
	 */
	public function render( \WP_User $user, ?string $requested_view ): void {
		$is_full_admin = current_user_can( 'msp_manage' );
		$active_view   = $this->resolve_active_view( $requested_view, $is_full_admin );
		?>
		<section class="uplinkportal-admin-hero">
			<div>
				<h1><?php echo esc_html__( 'Operations Dashboard', 'uplinkportal' ); ?></h1>
				<p>
					<?php
					echo esc_html__( 'Real-time operational metrics from your connected integrations. Data updates automatically based on your active modules.', 'uplinkportal' );
					?>
				</p>
			</div>
			<span class="uplinkportal-admin-badge">
				<?php echo esc_html( $this->view_label( $active_view ) ); ?>
			</span>
		</section>

		<?php if ( $is_full_admin ) : ?>
			<?php $this->render_view_switcher( $active_view ); ?>
		<?php endif; ?>

		<div class="uplinkportal-kpi-integration-note">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			<?php echo esc_html__( 'Dashboard data requires connected integrations. Configure your modules under Configuration > Modules to populate live metrics.', 'uplinkportal' ); ?>
		</div>

		<?php
		switch ( $active_view ) {
			case 'finance':
				$this->render_finance_view();
				break;
			case 'technician':
				$this->render_technician_view();
				break;
			default:
				$this->render_executive_view();
				break;
		}
	}

	/**
	 * Resolves the active view key based on user capabilities and the requested view.
	 *
	 * @param string|null $requested_view Sanitized view key from query arg.
	 * @param bool        $is_full_admin  Whether the user has msp_manage.
	 * @return string Active view key.
	 */
	private function resolve_active_view( ?string $requested_view, bool $is_full_admin ): string {
		if ( $is_full_admin ) {
			if ( null !== $requested_view && in_array( $requested_view, self::VALID_VIEWS, true ) ) {
				return $requested_view;
			}

			return 'executive';
		}

		if ( current_user_can( 'msp_view_financial_dashboard' ) ) {
			return 'finance';
		}

		if ( current_user_can( 'msp_view_technician_dashboard' ) ) {
			return 'technician';
		}

		return 'executive';
	}

	/**
	 * Renders the view switcher tab bar (only shown to msp_manage users).
	 *
	 * @param string $active_view Currently active view key.
	 * @return void
	 */
	private function render_view_switcher( string $active_view ): void {
		$views = array(
			'executive'  => array(
				'label' => __( 'Executive', 'uplinkportal' ),
				'icon'  => 'dashicons-chart-line',
			),
			'finance'    => array(
				'label' => __( 'Finance', 'uplinkportal' ),
				'icon'  => 'dashicons-money-alt',
			),
			'technician' => array(
				'label' => __( 'IT Technician', 'uplinkportal' ),
				'icon'  => 'dashicons-admin-tools',
			),
		);
		?>
		<nav class="uplinkportal-view-switcher" data-uplinkportal-view-switcher aria-label="<?php echo esc_attr__( 'Dashboard view', 'uplinkportal' ); ?>">
			<?php foreach ( $views as $view_key => $view_data ) : ?>
				<button
					type="button"
					class="uplinkportal-view-tab <?php echo $active_view === $view_key ? 'is-active' : ''; ?>"
					data-uplinkportal-view-tab="<?php echo esc_attr( $view_key ); ?>"
					aria-pressed="<?php echo $active_view === $view_key ? 'true' : 'false'; ?>"
				>
					<span class="dashicons <?php echo esc_attr( (string) $view_data['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( (string) $view_data['label'] ); ?>
				</button>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the Executive KPI view.
	 *
	 * @return void
	 */
	private function render_executive_view(): void {
		$kpis = $this->get_executive_kpis();
		?>
		<div class="uplinkportal-view-panel" data-uplinkportal-view-panel="executive">
			<div class="uplinkportal-kpi-grid">
				<?php foreach ( $kpis as $kpi ) : ?>
					<?php $this->render_kpi_card( $kpi ); ?>
				<?php endforeach; ?>
			</div>

			<div class="uplinkportal-admin-card">
				<h2><?php echo esc_html__( 'Module Health', 'uplinkportal' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Summary of enabled modules and their integration status. Configure modules to see live data.', 'uplinkportal' ); ?>
				</p>
				<div class="uplinkportal-metric-list">
					<?php
					/**
					 * Fires to render executive module health rows.
					 * By default shows a placeholder row.
					 *
					 * @since 0.2.0
					 */
					do_action( 'uplinkportal_dashboard_executive_module_health' );
					?>
					<article class="uplinkportal-metric-row">
						<div>
							<h3><?php echo esc_html__( 'Integrations', 'uplinkportal' ); ?></h3>
							<p><?php echo esc_html__( 'Connect modules to display active integration count.', 'uplinkportal' ); ?></p>
						</div>
						<span class="uplinkportal-status-pill is-disabled"><?php echo esc_html__( 'No data', 'uplinkportal' ); ?></span>
					</article>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Finance KPI view.
	 *
	 * @return void
	 */
	private function render_finance_view(): void {
		$kpis = $this->get_finance_kpis();
		?>
		<div class="uplinkportal-view-panel" data-uplinkportal-view-panel="finance">
			<div class="uplinkportal-kpi-grid">
				<?php foreach ( $kpis as $kpi ) : ?>
					<?php $this->render_kpi_card( $kpi ); ?>
				<?php endforeach; ?>
			</div>

			<div class="uplinkportal-admin-card">
				<h2><?php echo esc_html__( 'Revenue Summary', 'uplinkportal' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Financial trend data from connected billing integrations (e.g. Invoice Ninja).', 'uplinkportal' ); ?>
				</p>
				<p><?php echo esc_html__( 'Connect a billing integration under Configuration > Modules to populate financial charts.', 'uplinkportal' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the IT Technician KPI view.
	 *
	 * @return void
	 */
	private function render_technician_view(): void {
		$kpis = $this->get_technician_kpis();
		?>
		<div class="uplinkportal-view-panel" data-uplinkportal-view-panel="technician">
			<div class="uplinkportal-kpi-grid">
				<?php foreach ( $kpis as $kpi ) : ?>
					<?php $this->render_kpi_card( $kpi ); ?>
				<?php endforeach; ?>
			</div>

			<div class="uplinkportal-admin-card">
				<h2><?php echo esc_html__( 'Priority Tickets', 'uplinkportal' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Open high-priority incidents from your connected help desk integration.', 'uplinkportal' ); ?>
				</p>
				<p><?php echo esc_html__( 'Connect an IT support integration under Configuration > Modules to see live ticket queues.', 'uplinkportal' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a single KPI card widget.
	 *
	 * @param array<string,string> $kpi KPI data array with keys: label, value, sub, indicator, indicator_dir.
	 * @return void
	 */
	private function render_kpi_card( array $kpi ): void {
		$label         = isset( $kpi['label'] ) ? (string) $kpi['label'] : '';
		$value         = isset( $kpi['value'] ) ? (string) $kpi['value'] : '—';
		$sub           = isset( $kpi['sub'] ) ? (string) $kpi['sub'] : '';
		$indicator     = isset( $kpi['indicator'] ) ? (string) $kpi['indicator'] : '';
		$indicator_dir = isset( $kpi['indicator_dir'] ) ? (string) $kpi['indicator_dir'] : '';
		?>
		<article class="uplinkportal-kpi-card">
			<p class="uplinkportal-kpi-card-label"><?php echo esc_html( $label ); ?></p>
			<p class="uplinkportal-kpi-card-value"><?php echo esc_html( $value ); ?></p>
			<?php if ( '' !== $sub ) : ?>
				<p class="uplinkportal-kpi-card-sub"><?php echo esc_html( $sub ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $indicator ) : ?>
				<p class="uplinkportal-kpi-card-indicator <?php echo 'up' === $indicator_dir ? 'is-up' : 'is-down'; ?>">
					<span class="dashicons <?php echo 'up' === $indicator_dir ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt'; ?>" aria-hidden="true"></span>
					<?php echo esc_html( $indicator ); ?>
				</p>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * Returns placeholder KPI definitions for the Executive view.
	 *
	 * Real integrations replace these via the `uplinkportal_dashboard_kpi_executive` filter.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_executive_kpis(): array {
		$defaults = array(
			array(
				'label' => __( 'Active Clients', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'MRR', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires billing integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Open Tickets', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Avg. Response Time', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
		);

		/**
		 * Filters Executive dashboard KPI cards.
		 *
		 * @param array<int,array<string,string>> $kpis KPI card definitions.
		 */
		return (array) apply_filters( 'uplinkportal_dashboard_kpi_executive', $defaults );
	}

	/**
	 * Returns placeholder KPI definitions for the Finance view.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_finance_kpis(): array {
		$defaults = array(
			array(
				'label' => __( 'Outstanding Invoices', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires billing integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'MRR', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires billing integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Paid This Month', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires billing integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Overdue Balances', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires billing integration', 'uplinkportal' ),
			),
		);

		/**
		 * Filters Finance dashboard KPI cards.
		 *
		 * @param array<int,array<string,string>> $kpis KPI card definitions.
		 */
		return (array) apply_filters( 'uplinkportal_dashboard_kpi_finance', $defaults );
	}

	/**
	 * Returns placeholder KPI definitions for the Technician view.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_technician_kpis(): array {
		$defaults = array(
			array(
				'label' => __( 'Open Tickets', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Avg. First Response', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'SLA Compliance', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
			array(
				'label' => __( 'Critical Priority', 'uplinkportal' ),
				'value' => '—',
				'sub'   => __( 'Requires help desk integration', 'uplinkportal' ),
			),
		);

		/**
		 * Filters Technician dashboard KPI cards.
		 *
		 * @param array<int,array<string,string>> $kpis KPI card definitions.
		 */
		return (array) apply_filters( 'uplinkportal_dashboard_kpi_technician', $defaults );
	}

	/**
	 * Returns the human-readable label for a view key.
	 *
	 * @param string $view_key View key ('executive'|'finance'|'technician').
	 * @return string
	 */
	private function view_label( string $view_key ): string {
		$labels = array(
			'executive'  => __( 'Executive View', 'uplinkportal' ),
			'finance'    => __( 'Finance View', 'uplinkportal' ),
			'technician' => __( 'Technician View', 'uplinkportal' ),
		);

		return isset( $labels[ $view_key ] ) ? (string) $labels[ $view_key ] : __( 'Dashboard', 'uplinkportal' );
	}
}
