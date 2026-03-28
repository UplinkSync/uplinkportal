<?php
/**
 * Configuration > Security tab UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Configuration > Security tab body.
 *
 * Data is pre-computed by SettingsPage and passed in via render(). Groups
 * Security tab fields into named cards and adds a Security Health Score dial.
 */
class ConfigSecurityUi {

	/**
	 * Renders the full Security tab body.
	 *
	 * @param bool                           $strict_nonce          Whether strict REST nonce enforcement is active.
	 * @param bool                           $show_audit            Whether the audit log is visible.
	 * @param bool                           $mask_audit            Whether audit log text is masked.
	 * @param bool                           $trail_enabled         Whether the evidence trail is enabled.
	 * @param bool                           $private_doc_storage   Whether documents are stored in a private directory.
	 * @param bool                           $salt_is_placeholder   Whether the crypto salt is the default placeholder.
	 * @param bool                           $security_module_active Whether the Security & Compliance module is active.
	 * @param array<int,array<string,mixed>> $alerts               Recent security alerts.
	 * @param array<int,array<string,mixed>> $evidence             Recent evidence trail entries.
	 * @param array<string,mixed>            $trail_state          Chain verification state from EvidenceTrailStore::verify_chain().
	 * @param array<int,array<string,mixed>> $audit_entries        Recent CapabilityManager audit log entries.
	 * @param array<int,array<string,mixed>> $central_audit        Recent CentralAuditJournal entries.
	 * @param string                         $resync_url           URL for role resync form action redirect.
	 * @param string                         $resync_status        Status key from query arg after role resync.
	 * @param string                         $clients_url          URL to Client Access tab.
	 * @param string                         $modules_url          URL to Modules tab.
	 * @return void
	 */
	public function render(
		bool $strict_nonce,
		bool $show_audit,
		bool $mask_audit,
		bool $trail_enabled,
		bool $private_doc_storage,
		bool $salt_is_placeholder,
		bool $security_module_active,
		array $alerts,
		array $evidence,
		array $trail_state,
		array $audit_entries,
		array $central_audit,
		string $resync_url,
		string $resync_status,
		string $clients_url,
		string $modules_url
	): void {
		$score = $this->compute_health_score( $strict_nonce, $show_audit, $trail_enabled, $salt_is_placeholder );
		$this->render_health_score_card( $score );
		$this->render_access_card( $strict_nonce, $show_audit );
		$this->render_data_protection_card( $private_doc_storage, $salt_is_placeholder );

		if ( $security_module_active ) {
			$this->render_alerts_card( $alerts, $mask_audit );
			$this->render_evidence_trail_card( $trail_enabled, $trail_state, $evidence, $mask_audit );
			$this->render_role_management_card( $resync_url, $resync_status, $clients_url, $modules_url );
		} else {
			?>
			<div class="uplinkportal-admin-card">
				<div class="notice notice-info inline" style="margin:0;">
					<p><?php echo esc_html__( 'Security baseline controls are active. Advanced monitoring and evidence trail controls expand when the Security & Compliance module entitlement is active.', 'uplinkportal' ); ?></p>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Computes a 0–100 Security Health Score.
	 *
	 * Scoring criteria (25 pts each):
	 *   - Strict REST nonce enforcement enabled
	 *   - Audit log visibility enabled
	 *   - Evidence trail enabled
	 *   - Crypto salt is not the default placeholder
	 *
	 * @param bool $strict_nonce        Nonce enforcement state.
	 * @param bool $show_audit          Audit log visibility state.
	 * @param bool $trail_enabled       Evidence trail enabled state.
	 * @param bool $salt_is_placeholder Whether crypto salt is the default placeholder.
	 * @return int Score 0–100.
	 */
	private function compute_health_score(
		bool $strict_nonce,
		bool $show_audit,
		bool $trail_enabled,
		bool $salt_is_placeholder
	): int {
		$score = 0;
		if ( $strict_nonce ) {
			$score += 25;
		}
		if ( $show_audit ) {
			$score += 25;
		}
		if ( $trail_enabled ) {
			$score += 25;
		}
		if ( ! $salt_is_placeholder ) {
			$score += 25;
		}
		return $score;
	}

	/**
	 * Renders the Security Health Score card with a conic-gradient dial.
	 *
	 * @param int $score Score 0–100.
	 * @return void
	 */
	private function render_health_score_card( int $score ): void {
		$color = $score >= 75 ? '#22c55e' : ( $score >= 50 ? '#f59e0b' : '#ef4444' );
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Security Health Score', 'uplinkportal' ); ?></h3>
			</div>
			<div class="uplinkportal-health-score" aria-label="<?php echo esc_attr( sprintf( /* translators: %d is a score out of 100. */ __( 'Security health score: %d out of 100', 'uplinkportal' ), $score ) ); ?>">
				<div
					class="uplinkportal-health-score-dial"
					style="background: conic-gradient(<?php echo esc_attr( $color ); ?> <?php echo esc_attr( (string) $score ); ?>%, #e2e8f0 0);"
					role="img"
					aria-hidden="true"
				>
					<div class="uplinkportal-health-score-inner">
						<span><?php echo esc_html( (string) $score ); ?></span>
					</div>
				</div>
				<div class="uplinkportal-health-score-legend">
					<p class="description">
						<?php
						if ( $score >= 75 ) {
							echo esc_html__( 'Good posture — all critical controls are active.', 'uplinkportal' );
						} elseif ( $score >= 50 ) {
							echo esc_html__( 'Moderate — review the items below to improve your score.', 'uplinkportal' );
						} else {
							echo esc_html__( 'Attention needed — several security controls are inactive.', 'uplinkportal' );
						}
						?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Authentication & Access card.
	 *
	 * @param bool $strict_nonce Nonce enforcement state.
	 * @param bool $show_audit   Audit log visibility state.
	 * @return void
	 */
	private function render_access_card( bool $strict_nonce, bool $show_audit ): void {
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-lock" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Authentication & Access', 'uplinkportal' ); ?></h3>
			</div>
			<ul class="uplinkportal-security-checklist">
				<li class="<?php echo $strict_nonce ? 'is-active' : 'is-inactive'; ?>">
					<span class="dashicons <?php echo $strict_nonce ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					<?php
					echo esc_html(
						$strict_nonce
							? __( 'REST nonce enforcement: Enabled', 'uplinkportal' )
							: __( 'REST nonce enforcement: Disabled — enable via the uplinkportal_enforce_rest_nonce filter', 'uplinkportal' )
					);
					?>
				</li>
				<li class="<?php echo $show_audit ? 'is-active' : 'is-inactive'; ?>">
					<span class="dashicons <?php echo $show_audit ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					<?php
					echo esc_html(
						$show_audit
							? __( 'Audit log visibility: Enabled', 'uplinkportal' )
							: __( 'Audit log visibility: Hidden', 'uplinkportal' )
					);
					?>
				</li>
				<li class="is-active">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php echo esc_html__( 'Admin capability checks: Enforced on all privileged routes', 'uplinkportal' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders the Data Protection card.
	 *
	 * @param bool $private_doc_storage Whether documents use private storage.
	 * @param bool $salt_is_placeholder Whether the crypto salt is the placeholder value.
	 * @return void
	 */
	private function render_data_protection_card( bool $private_doc_storage, bool $salt_is_placeholder ): void {
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-database-view" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Data Protection', 'uplinkportal' ); ?></h3>
			</div>
			<ul class="uplinkportal-security-checklist">
				<li class="<?php echo $private_doc_storage ? 'is-active' : 'is-warning'; ?>">
					<span class="dashicons <?php echo $private_doc_storage ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					<?php
					echo esc_html(
						$private_doc_storage
							? __( 'Document storage: Private directory (outside public uploads)', 'uplinkportal' )
							: __( 'Document storage: Fallback hardened path — configure a private directory for stronger isolation', 'uplinkportal' )
					);
					?>
				</li>
				<li class="<?php echo ! $salt_is_placeholder ? 'is-active' : 'is-inactive'; ?>">
					<span class="dashicons <?php echo ! $salt_is_placeholder ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
					<?php
					echo esc_html(
						! $salt_is_placeholder
							? __( 'Crypto salt: Custom value configured', 'uplinkportal' )
							: __( 'Crypto salt: Using placeholder — set UPLINKPORTAL_CRYPTO_SALT in wp-config.php', 'uplinkportal' )
					);
					?>
				</li>
				<li class="is-active">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php echo esc_html__( 'Secret values: Stored in dedicated option keys and masked in admin views', 'uplinkportal' ); ?>
				</li>
				<li class="is-active">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php echo esc_html__( 'Outgoing HTTP: HTTPS enforced by transport security policy', 'uplinkportal' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders the Monitoring & Alerts card.
	 *
	 * @param array<int,array<string,mixed>> $alerts     Recent security alerts.
	 * @param bool                           $mask_audit Whether to mask alert message text.
	 * @return void
	 */
	private function render_alerts_card( array $alerts, bool $mask_audit ): void {
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-bell" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Monitoring & Alerts', 'uplinkportal' ); ?></h3>
			</div>
			<?php if ( empty( $alerts ) ) : ?>
				<p><?php echo esc_html__( 'No security alerts have been triggered.', 'uplinkportal' ); ?></p>
			<?php else : ?>
				<div class="uplinkportal-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Time (UTC)', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Source', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Event', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Message', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Count', 'uplinkportal' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $alerts as $alert ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $alert['time'] ) ? (string) $alert['time'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $alert['source'] ) ? (string) $alert['source'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $alert['event'] ) ? (string) $alert['event'] : '' ); ?></td>
									<td>
										<?php
										$msg = isset( $alert['message'] ) ? (string) $alert['message'] : '';
										echo esc_html( $mask_audit ? '••••••' : $msg );
										?>
									</td>
									<td><?php echo esc_html( isset( $alert['count'] ) ? (string) absint( $alert['count'] ) : '0' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Evidence Trail card.
	 *
	 * @param bool                           $trail_enabled Whether the trail is enabled.
	 * @param array<string,mixed>            $trail_state   Chain verification state.
	 * @param array<int,array<string,mixed>> $evidence      Recent evidence entries.
	 * @param bool                           $mask_audit    Whether to mask entry text.
	 * @return void
	 */
	private function render_evidence_trail_card( bool $trail_enabled, array $trail_state, array $evidence, bool $mask_audit ): void {
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Evidence Trail', 'uplinkportal' ); ?></h3>
			</div>
			<?php if ( ! $trail_enabled ) : ?>
				<p><?php echo esc_html__( 'Evidence trail is currently disabled. Enable it via the uplinkportal_evidence_trail_enabled filter for tamper-evident forensic chain records.', 'uplinkportal' ); ?></p>
			<?php else : ?>
				<?php
				$chain_valid  = ! empty( $trail_state['valid'] );
				$entry_count  = isset( $trail_state['entry_count'] ) ? absint( $trail_state['entry_count'] ) : 0;
				$status_label = $chain_valid ? __( 'Valid', 'uplinkportal' ) : __( 'Integrity Check Failed', 'uplinkportal' );
				$status_class = $chain_valid ? 'uplinkportal-status-pill--active' : 'uplinkportal-status-pill--error';
				?>
				<p>
					<span class="uplinkportal-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d is the number of entries. */
							__( '%d entries recorded', 'uplinkportal' ),
							$entry_count
						)
					);
					?>
				</p>
				<?php if ( ! empty( $evidence ) ) : ?>
					<div class="uplinkportal-table-scroll">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Time (UTC)', 'uplinkportal' ); ?></th>
									<th><?php echo esc_html__( 'Source', 'uplinkportal' ); ?></th>
									<th><?php echo esc_html__( 'Event', 'uplinkportal' ); ?></th>
									<th><?php echo esc_html__( 'Result', 'uplinkportal' ); ?></th>
									<th><?php echo esc_html__( 'Hash', 'uplinkportal' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $evidence as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( isset( $entry['time'] ) ? (string) $entry['time'] : '' ); ?></td>
										<td><?php echo esc_html( isset( $entry['source'] ) ? (string) $entry['source'] : '' ); ?></td>
										<td><?php echo esc_html( $mask_audit ? '••••' : ( isset( $entry['event'] ) ? (string) $entry['event'] : '' ) ); ?></td>
										<td><?php echo esc_html( isset( $entry['result'] ) ? (string) $entry['result'] : '' ); ?></td>
										<td><code><?php echo esc_html( isset( $entry['hash'] ) ? substr( (string) $entry['hash'], 0, 16 ) . '...' : '' ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Role Management card with role resync form.
	 *
	 * @param string $resync_url   URL for the role resync redirect.
	 * @param string $resync_status Status key from query arg.
	 * @param string $clients_url  URL to Client Access tab.
	 * @param string $modules_url  URL to Modules tab.
	 * @return void
	 */
	private function render_role_management_card( string $resync_url, string $resync_status, string $clients_url, string $modules_url ): void {
		?>
		<div class="uplinkportal-admin-card uplinkportal-security-group">
			<div class="uplinkportal-security-group-header">
				<span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<h3><?php echo esc_html__( 'Role Management', 'uplinkportal' ); ?></h3>
			</div>
			<?php if ( 'success' === $resync_status ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'Roles were successfully resynced.', 'uplinkportal' ); ?></p></div>
			<?php elseif ( 'denied' === $resync_status ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html__( 'Role resync was blocked due to missing permissions.', 'uplinkportal' ); ?></p></div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Use role resync if UplinkPortal roles appear missing or after a plugin update that adds new capabilities.', 'uplinkportal' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'uplinkportal_resync_roles_action', 'uplinkportal_resync_roles_nonce' ); ?>
				<input type="hidden" name="action" value="uplinkportal_resync_roles" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $resync_url ); ?>" />
				<?php submit_button( __( 'Resync Roles', 'uplinkportal' ), 'secondary', 'submit', false ); ?>
			</form>
			<p class="description" style="margin-top:12px;">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: client access URL, 2: modules URL. */
						__( 'After resyncing: go to <a href="%1$s">Client Access</a> to assign roles, or <a href="%2$s">Modules</a> to configure feature visibility.', 'uplinkportal' ),
						esc_url( $clients_url ),
						esc_url( $modules_url )
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
