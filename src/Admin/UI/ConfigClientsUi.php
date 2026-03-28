<?php
/**
 * Configuration > Client Access UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Configuration > Client Access tab with a searchable matrix table.
 *
 * The matrix shows one row per client user and one column per enabled module,
 * with access state cells (enabled/inherit/disabled) that link to override modals.
 *
 * The underlying data model (ClientAccessStore) is unchanged; this is a new
 * presentation layer over the existing per-client module access records.
 */
class ConfigClientsUi {

	/**
	 * Renders the Client Access Matrix.
	 *
	 * @param array<int,\WP_User>               $clients          Client users with `client_access` capability.
	 * @param array<string,array<string,mixed>> $module_labels    Map of module_key => ['label' => string, 'enabled' => bool].
	 * @param array<string,array<string,mixed>> $plan_templates   Available plan templates for the plan dropdown.
	 * @param array<string,array<string,mixed>> $matrix_rows      Pre-computed access data: client_id => ['plan' => string, 'overrides' => array, 'identity' => array].
	 * @param int                               $saved            1 if matrix was just saved.
	 * @param int                               $entitlements_saved 1 if entitlements were just saved.
	 * @return void
	 */
	public function render( array $clients, array $module_labels, array $plan_templates, array $matrix_rows, int $saved, int $entitlements_saved ): void {
		?>
		<h2><?php echo esc_html__( 'Client Access Matrix', 'uplinkportal' ); ?></h2>
		<p>
			<?php echo esc_html__( 'Assign each client to a plan template, then use module columns to apply per-client overrides. Click any module cell to edit access.', 'uplinkportal' ); ?>
		</p>

		<?php if ( 1 === $saved ) : ?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Client access matrix saved.', 'uplinkportal' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( 1 === $entitlements_saved ) : ?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Client entitlements saved.', 'uplinkportal' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( empty( $clients ) ) : ?>
			<p><?php echo esc_html__( 'No client users found. Assign users to UplinkPortal client roles to manage access here.', 'uplinkportal' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'uplinkportal_save_client_matrix', 'uplinkportal_client_matrix_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_save_client_matrix" />

			<div class="uplinkportal-matrix-search-bar">
				<label for="uplinkportal-matrix-search" class="screen-reader-text">
					<?php echo esc_html__( 'Filter clients', 'uplinkportal' ); ?>
				</label>
				<input
					type="search"
					id="uplinkportal-matrix-search"
					placeholder="<?php echo esc_attr__( 'Filter clients…', 'uplinkportal' ); ?>"
					data-uplinkportal-matrix-search
				/>
			</div>

			<div class="uplinkportal-matrix-wrap">
				<table class="uplinkportal-matrix-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Client', 'uplinkportal' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Plan', 'uplinkportal' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Override', 'uplinkportal' ); ?></th>
							<?php foreach ( $module_labels as $module_key => $module_data ) : ?>
								<th scope="col"><?php echo esc_html( (string) $module_data['label'] ); ?></th>
							<?php endforeach; ?>
							<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'uplinkportal' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $clients as $client_user ) : ?>
							<?php
							$client_id    = absint( $client_user->ID );
							$row          = isset( $matrix_rows[ $client_id ] ) ? $matrix_rows[ $client_id ] : array();
							$current_plan = isset( $row['plan'] ) ? (string) $row['plan'] : 'starter';
							$overrides    = isset( $row['overrides'] ) && is_array( $row['overrides'] ) ? $row['overrides'] : array();
							$identity     = isset( $row['identity'] ) && is_array( $row['identity'] ) ? $row['identity'] : array();
							$override_on  = ! empty( $identity['override_enabled'] );
							$modal_id     = 'uplinkportal-override-modal-' . $client_id;
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $client_user->display_name ); ?></strong><br />
									<code><?php echo esc_html( $client_user->user_email ); ?></code>
								</td>
								<td>
									<label for="uplinkportal-plan-<?php echo esc_attr( (string) $client_id ); ?>" class="screen-reader-text">
										<?php echo esc_html__( 'Plan', 'uplinkportal' ); ?>
									</label>
									<select
										id="uplinkportal-plan-<?php echo esc_attr( (string) $client_id ); ?>"
										name="client_plan[<?php echo esc_attr( (string) $client_id ); ?>]"
									>
										<?php foreach ( $plan_templates as $plan_key => $plan_data ) : ?>
											<option
												value="<?php echo esc_attr( (string) $plan_key ); ?>"
												<?php selected( $plan_key, $current_plan ); ?>
											>
												<?php echo esc_html( isset( $plan_data['label'] ) ? (string) $plan_data['label'] : (string) $plan_key ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<label class="uplinkportal-toggle" title="<?php echo esc_attr__( 'Enable per-client overrides', 'uplinkportal' ); ?>">
										<input
											type="checkbox"
											class="uplinkportal-toggle-input"
											name="client_override_enabled[<?php echo esc_attr( (string) $client_id ); ?>]"
											value="1"
											<?php checked( $override_on ); ?>
										/>
										<span class="uplinkportal-toggle-track" aria-hidden="true">
											<span class="uplinkportal-toggle-thumb"></span>
										</span>
									</label>
								</td>

								<?php foreach ( $module_labels as $module_key => $module_data ) : ?>
									<?php
									$state = isset( $overrides[ $module_key ] ) ? (string) $overrides[ $module_key ] : 'inherit';
									if ( ! in_array( $state, array( 'enabled', 'disabled', 'inherit' ), true ) ) {
										$state = 'inherit';
									}

									$cell_class = 'uplinkportal-matrix-cell--' . $state;
									$cell_icon  = array(
										'enabled'  => '✓',
										'disabled' => '✗',
										'inherit'  => '—',
									);
									$cell_label = array(
										'enabled'  => __( 'Enabled', 'uplinkportal' ),
										'disabled' => __( 'Disabled', 'uplinkportal' ),
										'inherit'  => __( 'From plan', 'uplinkportal' ),
									);
									?>
									<td>
										<button
											type="button"
											class="uplinkportal-matrix-cell <?php echo esc_attr( $cell_class ); ?>"
											data-uplinkportal-open-override-modal="<?php echo esc_attr( $modal_id ); ?>"
											title="<?php echo esc_attr( isset( $cell_label[ $state ] ) ? (string) $cell_label[ $state ] : $state ); ?>"
											aria-label="<?php echo esc_attr( sprintf( '%s: %s', $client_user->display_name, isset( $cell_label[ $state ] ) ? (string) $cell_label[ $state ] : $state ) ); ?>"
										>
											<?php echo esc_html( isset( $cell_icon[ $state ] ) ? (string) $cell_icon[ $state ] : '—' ); ?>
										</button>
									</td>
								<?php endforeach; ?>

								<td>
									<button
										type="button"
										class="button button-secondary"
										data-uplinkportal-open-override-modal="<?php echo esc_attr( $modal_id ); ?>"
									>
										<?php echo esc_html__( 'Edit', 'uplinkportal' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php submit_button( __( 'Save Matrix', 'uplinkportal' ), 'primary', 'submit', true, array( 'style' => 'margin-top: 16px;' ) ); ?>
		</form>

		<?php $this->render_override_modals( $clients, $module_labels, $matrix_rows ); ?>
		<?php
	}

	/**
	 * Renders hidden override modals for each client (reuses existing modal pattern).
	 *
	 * @param array<int,\WP_User>               $clients       Client users.
	 * @param array<string,array<string,mixed>> $module_labels Module key => label data.
	 * @param array<string,array<string,mixed>> $matrix_rows   Pre-computed access data.
	 * @return void
	 */
	private function render_override_modals( array $clients, array $module_labels, array $matrix_rows ): void {
		foreach ( $clients as $client_user ) {
			$client_id = absint( $client_user->ID );
			$row       = isset( $matrix_rows[ $client_id ] ) ? $matrix_rows[ $client_id ] : array();
			$overrides = isset( $row['overrides'] ) && is_array( $row['overrides'] ) ? $row['overrides'] : array();
			$modal_id  = 'uplinkportal-override-modal-' . $client_id;
			// translators: %s is the client's display name.
			$modal_label = sprintf( __( 'Access overrides for %s', 'uplinkportal' ), $client_user->display_name );
			?>
			<div
				id="<?php echo esc_attr( $modal_id ); ?>"
				class="uplinkportal-override-modal"
				hidden
				role="dialog"
				aria-modal="true"
				aria-label="<?php echo esc_attr( $modal_label ); ?>"
			>
				<div class="uplinkportal-settings-window-dialog">
					<div class="uplinkportal-settings-window-dialog-head">
						<h3>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s client display name. */
									__( 'Access Overrides: %s', 'uplinkportal' ),
									$client_user->display_name
								)
							);
							?>
						</h3>
						<button
							type="button"
							class="uplinkportal-icon-action"
							data-uplinkportal-close-override-modal="<?php echo esc_attr( $modal_id ); ?>"
							aria-label="<?php echo esc_attr__( 'Close', 'uplinkportal' ); ?>"
						>
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php echo esc_html__( 'Close', 'uplinkportal' ); ?></span>
						</button>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'uplinkportal_save_client_overrides_' . $client_id, 'uplinkportal_client_overrides_nonce' ); ?>
						<input type="hidden" name="action" value="uplinkportal_save_client_overrides" />
						<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />

						<p class="description">
							<?php echo esc_html__( 'Set module-level exceptions for this client. "Inherit" follows the assigned plan template.', 'uplinkportal' ); ?>
						</p>

						<?php foreach ( $module_labels as $module_key => $module_data ) : ?>
							<?php $state = isset( $overrides[ $module_key ] ) ? (string) $overrides[ $module_key ] : 'inherit'; ?>
							<div style="margin-bottom: 12px;">
								<p><strong><?php echo esc_html( (string) $module_data['label'] ); ?></strong></p>
								<label>
									<input type="radio" name="module_overrides[<?php echo esc_attr( $module_key ); ?>]" value="enabled" <?php checked( $state, 'enabled' ); ?> />
									<?php echo esc_html__( 'Enabled', 'uplinkportal' ); ?>
								</label>
								<label style="margin-left: 12px;">
									<input type="radio" name="module_overrides[<?php echo esc_attr( $module_key ); ?>]" value="inherit" <?php checked( $state, 'inherit' ); ?> />
									<?php echo esc_html__( 'Inherit from plan', 'uplinkportal' ); ?>
								</label>
								<label style="margin-left: 12px;">
									<input type="radio" name="module_overrides[<?php echo esc_attr( $module_key ); ?>]" value="disabled" <?php checked( $state, 'disabled' ); ?> />
									<?php echo esc_html__( 'Disabled', 'uplinkportal' ); ?>
								</label>
							</div>
						<?php endforeach; ?>

						<?php submit_button( __( 'Save Overrides', 'uplinkportal' ), 'primary', 'submit_overrides' ); ?>
					</form>
				</div>
			</div>
			<?php
		}
	}
}
