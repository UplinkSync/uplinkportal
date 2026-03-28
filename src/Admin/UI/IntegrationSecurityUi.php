<?php
/**
 * Integration and security UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders reusable integration/security admin UI blocks.
 */
final class IntegrationSecurityUi {
	/**
	 * Renders policy bundle summary panel.
	 *
	 * @param string            $label Bundle label.
	 * @param string            $description Bundle description.
	 * @param array<int,string> $controls Control list.
	 * @return void
	 */
	public static function render_policy_bundle_panel( string $label, string $description, array $controls ): void {
		?>
		<h3><?php echo esc_html__( 'Compliance Policy Bundle', 'uplinkportal' ); ?></h3>
		<div class="notice notice-info inline">
			<p>
				<strong><?php echo esc_html__( 'Selected bundle:', 'uplinkportal' ); ?></strong>
				<?php echo esc_html( $label ); ?>
			</p>
			<?php if ( '' !== $description ) : ?>
				<p><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $controls ) ) : ?>
				<ul>
					<?php foreach ( $controls as $control ) : ?>
						<li><?php echo esc_html( sanitize_text_field( (string) $control ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders integration status board.
	 *
	 * @param array<int,array<string,mixed>> $rows Status rows.
	 * @return void
	 */
	public static function render_integration_status_board( array $rows ): void {
		?>
			<div class="uplinkportal-table-scroll">
				<table class="widefat striped uplinkportal-integration-status-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Model', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Connected', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Token Health', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Last Sync', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Policy Status', 'uplinkportal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( isset( $row['model'] ) ? (string) $row['model'] : '' ); ?></strong></td>
								<td><?php self::render_status_pill( isset( $row['connected'] ) && is_array( $row['connected'] ) ? $row['connected'] : array() ); ?></td>
								<td><?php self::render_status_pill( isset( $row['token_health'] ) && is_array( $row['token_health'] ) ? $row['token_health'] : array() ); ?></td>
								<td><?php echo esc_html( isset( $row['last_sync'] ) ? (string) $row['last_sync'] : '' ); ?></td>
								<td><?php self::render_status_pill( isset( $row['policy_status'] ) && is_array( $row['policy_status'] ) ? $row['policy_status'] : array() ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
	}

	/**
	 * Renders integration policy lint findings.
	 *
	 * @param array<string,array<int,array<string,string>>> $grouped Grouped findings.
	 * @return void
	 */
	public static function render_integration_policy_lint( array $grouped ): void {
		$has_findings = ! empty( $grouped['error'] ) || ! empty( $grouped['warning'] ) || ! empty( $grouped['info'] );
		?>
		<h3><?php echo esc_html__( 'Integration Policy Lint', 'uplinkportal' ); ?></h3>
		<?php if ( ! $has_findings ) : ?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'No integration profile policy issues detected.', 'uplinkportal' ); ?></p>
			</div>
		<?php else : ?>
			<p class="description"><?php echo esc_html__( 'Review these findings before finalizing connector activation.', 'uplinkportal' ); ?></p>
			<?php self::render_lint_group( 'error', isset( $grouped['error'] ) && is_array( $grouped['error'] ) ? $grouped['error'] : array() ); ?>
			<?php self::render_lint_group( 'warning', isset( $grouped['warning'] ) && is_array( $grouped['warning'] ) ? $grouped['warning'] : array() ); ?>
			<?php self::render_lint_group( 'info', isset( $grouped['info'] ) && is_array( $grouped['info'] ) ? $grouped['info'] : array() ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders harness panel.
	 *
	 * @param array<int,array<string,mixed>> $rows Harness rows.
	 * @return void
	 */
	public static function render_integration_harness_panel( array $rows ): void {
		?>
		<h3><?php echo esc_html__( 'Integration Profile Test Harness', 'uplinkportal' ); ?></h3>
		<p class="description"><?php echo esc_html__( 'Readiness checks for auth, schema, permission, and dry-run posture.', 'uplinkportal' ); ?></p>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php echo esc_html__( 'No integration profile rows are available.', 'uplinkportal' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
			<div class="uplinkportal-table-scroll">
				<table class="widefat striped uplinkportal-integration-harness-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Model', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Auth Test', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Schema Test', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Permission Test', 'uplinkportal' ); ?></th>
							<th><?php echo esc_html__( 'Dry-Run Test', 'uplinkportal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( isset( $row['model'] ) ? sanitize_text_field( (string) $row['model'] ) : __( 'Unknown', 'uplinkportal' ) ); ?></strong></td>
								<td><?php self::render_harness_cell( isset( $row['auth_test'] ) && is_array( $row['auth_test'] ) ? $row['auth_test'] : array() ); ?></td>
								<td><?php self::render_harness_cell( isset( $row['schema_test'] ) && is_array( $row['schema_test'] ) ? $row['schema_test'] : array() ); ?></td>
								<td><?php self::render_harness_cell( isset( $row['permission_test'] ) && is_array( $row['permission_test'] ) ? $row['permission_test'] : array() ); ?></td>
								<td><?php self::render_harness_cell( isset( $row['dry_run_test'] ) && is_array( $row['dry_run_test'] ) ? $row['dry_run_test'] : array() ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
	}

	/**
	 * Renders secret lease panel.
	 *
	 * @param array<int,string> $alerts Alert lines.
	 * @return void
	 */
	public static function render_secret_lease_panel( array $alerts ): void {
		?>
		<h3><?php echo esc_html__( 'Secret Lease Alerts', 'uplinkportal' ); ?></h3>
		<?php if ( empty( $alerts ) ) : ?>
			<p class="description"><?php echo esc_html__( 'All tracked integration secrets are within the recommended lease threshold.', 'uplinkportal' ); ?></p>
		<?php else : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php echo esc_html__( 'Action recommended:', 'uplinkportal' ); ?></strong> <?php echo esc_html__( 'Rotate or review stale integration secrets.', 'uplinkportal' ); ?></p>
				<ul>
					<?php foreach ( $alerts as $alert ) : ?>
						<li><?php echo esc_html( $alert ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders one status pill.
	 *
	 * @param array<string,string> $status Status cell.
	 * @return void
	 */
	private static function render_status_pill( array $status ): void {
		$label = isset( $status['label'] ) ? sanitize_text_field( (string) $status['label'] ) : __( 'Unknown', 'uplinkportal' );
		$state = isset( $status['state'] ) ? sanitize_key( (string) $status['state'] ) : 'warning';

		if ( ! in_array( $state, array( 'enabled', 'warning', 'disabled' ), true ) ) {
			$state = 'warning';
		}
		?>
		<span class="uplinkportal-status-pill is-<?php echo esc_attr( $state ); ?>">
			<?php echo esc_html( $label ); ?>
		</span>
		<?php
	}

	/**
	 * Renders one lint severity group.
	 *
	 * @param string                          $severity Severity key.
	 * @param array<int,array<string,string>> $findings Finding payloads.
	 * @return void
	 */
	private static function render_lint_group( string $severity, array $findings ): void {
		if ( empty( $findings ) ) {
			return;
		}

		$severity      = sanitize_key( $severity );
		$notice_class  = 'notice-warning';
		$heading_label = __( 'Warnings', 'uplinkportal' );

		if ( 'error' === $severity ) {
			$notice_class  = 'notice-error';
			$heading_label = __( 'Errors', 'uplinkportal' );
		} elseif ( 'info' === $severity ) {
			$notice_class  = 'notice-info';
			$heading_label = __( 'Advisories', 'uplinkportal' );
		}
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> inline">
			<p><strong><?php echo esc_html( $heading_label ); ?></strong></p>
			<ul>
				<?php foreach ( $findings as $finding ) : ?>
					<?php
					$scope          = isset( $finding['scope'] ) ? sanitize_text_field( (string) $finding['scope'] ) : __( 'General', 'uplinkportal' );
					$message        = isset( $finding['message'] ) ? sanitize_text_field( (string) $finding['message'] ) : '';
					$recommendation = isset( $finding['recommendation'] ) ? sanitize_text_field( (string) $finding['recommendation'] ) : '';
					?>
					<li>
						<strong><?php echo esc_html( $scope ); ?>:</strong>
						<?php echo esc_html( $message ); ?>
						<?php if ( '' !== $recommendation ) : ?>
							<?php echo esc_html( ' ' . $recommendation ); ?>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders one harness test cell.
	 *
	 * @param array<string,string> $result Result payload.
	 * @return void
	 */
	private static function render_harness_cell( array $result ): void {
		$label   = isset( $result['label'] ) ? sanitize_text_field( (string) $result['label'] ) : __( 'Needs Review', 'uplinkportal' );
		$state   = isset( $result['state'] ) ? sanitize_key( (string) $result['state'] ) : 'warning';
		$message = isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : '';

		if ( ! in_array( $state, array( 'enabled', 'warning', 'disabled' ), true ) ) {
			$state = 'warning';
		}
		?>
		<div class="uplinkportal-harness-cell">
			<span class="uplinkportal-status-pill uplinkportal-status-pill--compact is-<?php echo esc_attr( $state ); ?>">
				<?php echo esc_html( $label ); ?>
			</span>
			<?php if ( '' !== $message ) : ?>
				<p class="description"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
