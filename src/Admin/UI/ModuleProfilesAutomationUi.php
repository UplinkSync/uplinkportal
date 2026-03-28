<?php
/**
 * Modules profile/automation rendering helpers.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Handles shared UI rendering for module profile and automation sections.
 */
class ModuleProfilesAutomationUi {
	/**
	 * Renders integration profile cards wrapper.
	 *
	 * @param array    $allowed_models      Model ids.
	 * @param array    $profiles            Integration profiles map.
	 * @param callable $render_profile_card Card renderer callback.
	 * @return void
	 */
	public static function render_integration_profiles( array $allowed_models, array $profiles, callable $render_profile_card ): void {
		?>
		<p class="description"><?php echo esc_html__( 'Set provider, auth, scopes, retention policy, and lifecycle state for this module profile.', 'uplinkportal' ); ?></p>
		<div class="uplinkportal-integration-profile-grid">
			<?php foreach ( $allowed_models as $model_id ) : ?>
				<?php
				$model_id = sanitize_key( (string) $model_id );
				if ( '' === $model_id ) {
					continue;
				}
				$profile = isset( $profiles[ $model_id ] ) && is_array( $profiles[ $model_id ] ) ? $profiles[ $model_id ] : array();
				$render_profile_card( $model_id, $profile );
				?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders automation recipes table.
	 *
	 * @param array    $recipe_templates Recipe templates.
	 * @param array    $recipe_state     Saved recipe state.
	 * @param string   $field_base       Field base path.
	 * @param callable $get_revisions    Revision provider callback.
	 * @param array    $locked_models    Locked model ids.
	 * @return void
	 */
	public static function render_automation_recipes( array $recipe_templates, array $recipe_state, string $field_base, callable $get_revisions, array $locked_models = array() ): void {
		?>
		<p class="description"><?php echo esc_html__( 'Enable module-specific recipes in Dry Run or Live mode. Runtime execution stays delegated to your secure backend/add-on.', 'uplinkportal' ); ?></p>
		<table class="widefat striped uplinkportal-model-policy-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Recipe', 'uplinkportal' ); ?></th>
					<th><?php echo esc_html__( 'Compliance Labels', 'uplinkportal' ); ?></th>
					<th><?php echo esc_html__( 'Mode', 'uplinkportal' ); ?></th>
					<th><?php echo esc_html__( 'Enabled', 'uplinkportal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recipe_templates as $recipe_id => $recipe ) : ?>
					<?php
					$recipe_id              = sanitize_key( (string) $recipe_id );
					$entry                  = isset( $recipe_state[ $recipe_id ] ) && is_array( $recipe_state[ $recipe_id ] ) ? $recipe_state[ $recipe_id ] : array();
					$enabled                = ! empty( $entry['enabled'] );
					$mode                   = isset( $entry['mode'] ) ? sanitize_key( (string) $entry['mode'] ) : 'dry_run';
					$compliance_labels      = isset( $recipe['compliance_labels'] ) && is_array( $recipe['compliance_labels'] ) ? $recipe['compliance_labels'] : array();
						$revisions          = $get_revisions( $recipe_id );
						$recipe_label       = isset( $recipe['label'] ) ? (string) $recipe['label'] : (string) $recipe_id;
						$recipe_description = isset( $recipe['description'] ) ? (string) $recipe['description'] : '';
						$model_id           = isset( $recipe['model'] ) ? sanitize_key( (string) $recipe['model'] ) : '';
						$is_locked_model    = in_array( $model_id, $locked_models, true );
					?>
						<tr data-uplinkportal-recipe-model="<?php echo esc_attr( $model_id ); ?>">
							<td>
								<strong><?php echo esc_html( $recipe_label ); ?></strong><br />
								<span class="description"><?php echo esc_html( $recipe_description ); ?></span>
						</td>
						<td><?php echo esc_html( implode( ', ', array_map( 'sanitize_text_field', $compliance_labels ) ) ); ?></td>
						<td>
							<select name="<?php echo esc_attr( $field_base ); ?>[<?php echo esc_attr( $recipe_id ); ?>][mode]">
								<option value="dry_run" <?php selected( 'dry_run', $mode ); ?>><?php echo esc_html__( 'Dry Run', 'uplinkportal' ); ?></option>
								<option value="live" <?php selected( 'live', $mode ); ?>><?php echo esc_html__( 'Live', 'uplinkportal' ); ?></option>
							</select>
							<?php if ( ! empty( $revisions ) ) : ?>
								<div style="margin-top:8px;">
									<label>
										<?php echo esc_html__( 'Rollback Snapshot', 'uplinkportal' ); ?><br />
										<select name="<?php echo esc_attr( $field_base ); ?>[<?php echo esc_attr( $recipe_id ); ?>][rollback_revision_id]">
											<option value=""><?php echo esc_html__( 'Select a revision', 'uplinkportal' ); ?></option>
											<?php foreach ( $revisions as $revision ) : ?>
												<?php
												$revision_id    = isset( $revision['id'] ) ? sanitize_text_field( (string) $revision['id'] ) : '';
												$revision_time  = isset( $revision['time'] ) ? sanitize_text_field( (string) $revision['time'] ) : '';
												$revision_state = isset( $revision['state'] ) && is_array( $revision['state'] ) ? $revision['state'] : array();
												$state_mode     = isset( $revision_state['mode'] ) ? sanitize_key( (string) $revision_state['mode'] ) : 'dry_run';
												$state_enabled  = ! empty( $revision_state['enabled'] );
												$label_text     = sprintf(
													/* translators: 1: timestamp, 2: mode, 3: enabled/disabled. */
													__( '%1$s | %2$s | %3$s', 'uplinkportal' ),
													$revision_time,
													'dry_run' === $state_mode ? __( 'Dry Run', 'uplinkportal' ) : __( 'Live', 'uplinkportal' ),
													$state_enabled ? __( 'Enabled', 'uplinkportal' ) : __( 'Disabled', 'uplinkportal' )
												);
												?>
												<option value="<?php echo esc_attr( $revision_id ); ?>">
													<?php echo esc_html( $label_text ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
							<?php endif; ?>
						</td>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $field_base ); ?>[<?php echo esc_attr( $recipe_id ); ?>][enabled]" value="1" data-uplinkportal-recipe-enabled <?php checked( $enabled ); ?> <?php disabled( $is_locked_model ); ?> />
									<?php echo esc_html__( 'Enabled', 'uplinkportal' ); ?>
								</label>
							</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
