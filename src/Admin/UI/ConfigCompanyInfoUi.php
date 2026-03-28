<?php
/**
 * Configuration > Company Info tab UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Configuration > Company Info tab body.
 *
 * Data is pre-computed by SettingsPage and passed in via render(). Groups
 * company info fields into named card sections: Contact Information, Logo & Brand,
 * Office Hours, and Email Settings.
 */
class ConfigCompanyInfoUi {

	/**
	 * Renders the full Company Info tab body (form wrapper included).
	 *
	 * @param array<string,mixed>               $defaults           Merged customization defaults from PortalCustomizationStore.
	 * @param string                            $logo_url           Resolved logo URL (empty string if no logo selected).
	 * @param string                            $status             Status key from query arg (e.g. 'saved_company_information').
	 * @param array<string,string>              $state_options      US state code → label map.
	 * @param array<string,string>              $time_options       Time value → label map for operating hours dropdowns.
	 * @param array<string,string>              $day_labels         Day key → short label map (Mon, Tue, …).
	 * @param array<string,array<string,mixed>> $operating_schedule Per-day schedule from defaults.
	 * @param bool                              $is_country_us      Whether the saved country is United States.
	 * @param bool                              $smtp_plugin_available Whether a compatible SMTP plugin is installed.
	 * @param bool                              $show_custom_smtp   Whether the custom SMTP fields panel is visible.
	 * @return void
	 */
	public function render(
		array $defaults,
		string $logo_url,
		string $status,
		array $state_options,
		array $time_options,
		array $day_labels,
		array $operating_schedule,
		bool $is_country_us,
		bool $smtp_plugin_available,
		bool $show_custom_smtp
	): void {
		$this->render_status_notice( $status );
		?>
		<form
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			data-uplinkportal-customization-form
			enctype="multipart/form-data"
		>
			<?php wp_nonce_field( 'uplinkportal_save_customization_defaults', 'uplinkportal_customization_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_save_customization_defaults" />

			<div class="uplinkportal-card-row">
				<?php $this->render_contact_information_card( $defaults, $state_options, $is_country_us ); ?>
				<?php $this->render_logo_brand_card( $defaults, $logo_url ); ?>
			</div>
			<div class="uplinkportal-card-row">
				<?php $this->render_office_hours_card( $day_labels, $time_options, $operating_schedule ); ?>
				<?php $this->render_email_settings_card( $defaults, $smtp_plugin_available, $show_custom_smtp ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Renders status/error notices for customization saves.
	 *
	 * @param string $status Status key from query arg.
	 * @return void
	 */
	private function render_status_notice( string $status ): void {
		if ( in_array( $status, array( 'saved', 'saved_company_information', 'saved_company_logo', 'saved_operating_hours', 'saved_email' ), true ) ) {
			?>
			<div class="notice notice-success inline"><p><?php echo esc_html__( 'Section saved successfully.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_logo_format' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Logo must be a PNG or SVG file.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_logo_size' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Logo must be smaller than 200KB.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_logo_dimensions' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Logo dimensions cannot exceed 200x200 pixels.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_logo_missing' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Selected logo file could not be found. Choose another image.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_operating_hours_range' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Operating hours require opening time to be earlier than closing time.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_email_method' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Selected email delivery method is not available.', 'uplinkportal' ); ?></p></div>
			<?php
		} elseif ( 'error_smtp_fields' === $status ) {
			?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Complete all Custom SMTP fields with valid values.', 'uplinkportal' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Renders the Contact Information card.
	 *
	 * @param array<string,mixed>  $defaults      Customization defaults.
	 * @param array<string,string> $state_options US state code → label map.
	 * @param bool                 $is_country_us Whether the saved country is United States.
	 * @return void
	 */
	private function render_contact_information_card( array $defaults, array $state_options, bool $is_country_us ): void {
		?>
		<div class="uplinkportal-admin-card">
			<div class="uplinkportal-section-head">
				<h3><?php echo esc_html__( 'Contact Information', 'uplinkportal' ); ?></h3>
				<?php $this->render_section_save_button( 'company_information', __( 'Save Contact Information', 'uplinkportal' ) ); ?>
			</div>
			<div class="uplinkportal-customization-grid">
				<p>
					<label for="uplinkportal-company-name"><?php echo esc_html__( 'Company Name', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-company-name"
						name="company_name"
						value="<?php echo esc_attr( (string) $defaults['company_name'] ); ?>"
					/>
				</p>
				<p>
					<label for="uplinkportal-support-phone"><?php echo esc_html__( 'Office Number', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-support-phone"
						name="support_phone"
						value="<?php echo esc_attr( (string) $defaults['support_phone'] ); ?>"
					/>
				</p>
				<p class="uplinkportal-grid-col-span">
					<label for="uplinkportal-office-street"><?php echo esc_html__( 'Street Address', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-office-street"
						name="office_address_street"
						value="<?php echo esc_attr( (string) $defaults['office_address_street'] ); ?>"
					/>
				</p>
				<p>
					<label for="uplinkportal-office-city"><?php echo esc_html__( 'City', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-office-city"
						name="office_address_city"
						value="<?php echo esc_attr( (string) $defaults['office_address_city'] ); ?>"
					/>
				</p>
				<p>
					<label for="uplinkportal-office-country"><?php echo esc_html__( 'Country', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-office-country"
						name="office_address_country"
						value="<?php echo esc_attr( (string) $defaults['office_address_country'] ); ?>"
						data-uplinkportal-country
					/>
				</p>
				<p data-uplinkportal-us-state-wrap <?php echo $is_country_us ? '' : 'hidden'; ?>>
					<label for="uplinkportal-office-state"><?php echo esc_html__( 'State', 'uplinkportal' ); ?></label>
					<select
						id="uplinkportal-office-state"
						name="office_address_state"
						data-uplinkportal-us-state
						<?php disabled( ! $is_country_us ); ?>
					>
						<option value=""><?php echo esc_html__( 'Select a state', 'uplinkportal' ); ?></option>
						<?php foreach ( $state_options as $state_code => $state_label ) : ?>
							<option
								value="<?php echo esc_attr( $state_code ); ?>"
								<?php selected( $state_code, (string) $defaults['office_address_state'] ); ?>
							><?php echo esc_html( $state_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p data-uplinkportal-region-wrap <?php echo $is_country_us ? 'hidden' : ''; ?>>
					<label for="uplinkportal-office-region"><?php echo esc_html__( 'State / Region', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-office-region"
						name="office_address_region"
						value="<?php echo esc_attr( (string) $defaults['office_address_state'] ); ?>"
						data-uplinkportal-region
						<?php disabled( $is_country_us ); ?>
					/>
				</p>
				<p>
					<label for="uplinkportal-office-postal"><?php echo esc_html__( 'Zip / Postal Code', 'uplinkportal' ); ?></label>
					<input
						type="text"
						class="regular-text"
						id="uplinkportal-office-postal"
						name="office_address_postal_code"
						value="<?php echo esc_attr( (string) $defaults['office_address_postal_code'] ); ?>"
					/>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Logo & Brand card.
	 *
	 * @param array<string,mixed> $defaults Customization defaults.
	 * @param string              $logo_url Resolved thumbnail URL (empty if none).
	 * @return void
	 */
	private function render_logo_brand_card( array $defaults, string $logo_url ): void {
		$logo_id   = isset( $defaults['company_logo_id'] ) ? absint( $defaults['company_logo_id'] ) : 0;
		$logo_help = __( 'Use PNG or SVG under 200KB. Logo dimensions can be up to 200×200 pixels.', 'uplinkportal' );
		?>
		<div class="uplinkportal-admin-card">
			<div class="uplinkportal-section-head">
				<h3><?php echo esc_html__( 'Logo & Brand', 'uplinkportal' ); ?></h3>
				<?php $this->render_section_save_button( 'company_logo', __( 'Save Logo', 'uplinkportal' ) ); ?>
			</div>
			<div class="uplinkportal-customization-grid">
				<div>
					<p class="description"><?php echo esc_html__( 'Brand color is configured in Configuration > Portal Preview.', 'uplinkportal' ); ?></p>
				</div>
				<div class="uplinkportal-logo-control uplinkportal-grid-col-span">
					<input
						type="hidden"
						id="uplinkportal-company-logo-id"
						name="company_logo_id"
						value="<?php echo esc_attr( (string) $logo_id ); ?>"
					/>
					<div class="uplinkportal-logo-preview-wrap" id="uplinkportal-logo-preview">
						<?php if ( '' !== $logo_url ) : ?>
							<img
								src="<?php echo esc_url( $logo_url ); ?>"
								alt="<?php echo esc_attr__( 'Company logo preview', 'uplinkportal' ); ?>"
							/>
						<?php else : ?>
							<span><?php echo esc_html__( 'No logo selected yet.', 'uplinkportal' ); ?></span>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button button-secondary" id="uplinkportal-logo-change">
							<?php echo esc_html__( 'Change Logo', 'uplinkportal' ); ?>
						</button>
					</p>
					<p class="description uplinkportal-logo-footnote"><?php echo esc_html( $logo_help ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Office Hours card.
	 *
	 * @param array<string,string>              $day_labels         Day key → short label map.
	 * @param array<string,string>              $time_options       Time value → label map.
	 * @param array<string,array<string,mixed>> $operating_schedule Per-day schedule data.
	 * @return void
	 */
	private function render_office_hours_card( array $day_labels, array $time_options, array $operating_schedule ): void {
		?>
		<div class="uplinkportal-admin-card">
			<div class="uplinkportal-section-head">
				<h3><?php echo esc_html__( 'Office Hours', 'uplinkportal' ); ?></h3>
				<?php $this->render_section_save_button( 'operating_hours', __( 'Save Office Hours', 'uplinkportal' ) ); ?>
			</div>
			<p class="description"><?php echo esc_html__( 'Enable days your team is available. Opening time must be earlier than closing time for each enabled day.', 'uplinkportal' ); ?></p>
			<div class="uplinkportal-hours-list">
				<?php foreach ( $day_labels as $day_key => $day_label ) : ?>
					<?php
					$day_schedule = isset( $operating_schedule[ $day_key ] ) && is_array( $operating_schedule[ $day_key ] ) ? $operating_schedule[ $day_key ] : array();
					$is_enabled   = ! empty( $day_schedule['enabled'] );
					$open_value   = isset( $day_schedule['open'] ) && array_key_exists( (string) $day_schedule['open'], $time_options ) ? (string) $day_schedule['open'] : '09:00';
					$close_value  = isset( $day_schedule['close'] ) && array_key_exists( (string) $day_schedule['close'], $time_options ) ? (string) $day_schedule['close'] : '17:00';
					?>
					<div class="uplinkportal-hours-day <?php echo $is_enabled ? 'is-enabled' : ''; ?>" data-uplinkportal-hours-day>
						<label class="uplinkportal-hours-toggle">
							<input
								type="checkbox"
								name="operating_schedule[<?php echo esc_attr( $day_key ); ?>][enabled]"
								value="1"
								<?php checked( $is_enabled ); ?>
								data-uplinkportal-hours-enabled
							/>
							<span class="uplinkportal-hours-day-label"><?php echo esc_html( $day_label ); ?></span>
						</label>
						<div class="uplinkportal-hours-time-fields" data-uplinkportal-hours-times>
							<label>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s is a day name like Monday. */
											__( '%s opening time', 'uplinkportal' ),
											$day_label
										)
									);
									?>
								</span>
								<select
									name="operating_schedule[<?php echo esc_attr( $day_key ); ?>][open]"
									<?php disabled( ! $is_enabled ); ?>
									data-uplinkportal-hours-open
								>
									<?php foreach ( $time_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $open_value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<span aria-hidden="true">–</span>
							<label>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s is a day name like Monday. */
											__( '%s closing time', 'uplinkportal' ),
											$day_label
										)
									);
									?>
								</span>
								<select
									name="operating_schedule[<?php echo esc_attr( $day_key ); ?>][close]"
									<?php disabled( ! $is_enabled ); ?>
									data-uplinkportal-hours-close
								>
									<?php foreach ( $time_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $close_value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Email Settings card.
	 *
	 * @param array<string,mixed> $defaults              Customization defaults.
	 * @param bool                $smtp_plugin_available Whether a compatible SMTP plugin is detected.
	 * @param bool                $show_custom_smtp      Whether the Custom SMTP fields panel is visible.
	 * @return void
	 */
	private function render_email_settings_card( array $defaults, bool $smtp_plugin_available, bool $show_custom_smtp ): void {
		?>
		<div class="uplinkportal-admin-card">
			<div class="uplinkportal-section-head">
				<h3><?php echo esc_html__( 'Email Settings', 'uplinkportal' ); ?></h3>
				<?php $this->render_section_save_button( 'email', __( 'Save Email Settings', 'uplinkportal' ) ); ?>
			</div>
			<div class="uplinkportal-customization-grid">
				<p class="uplinkportal-grid-col-span">
					<label for="uplinkportal-support-email"><?php echo esc_html__( 'Support Email Address', 'uplinkportal' ); ?></label>
					<input
						type="email"
						class="regular-text"
						id="uplinkportal-support-email"
						name="support_email"
						value="<?php echo esc_attr( (string) $defaults['support_email'] ); ?>"
					/>
				</p>
			</div>
			<div class="uplinkportal-email-methods uplinkportal-email-methods-wrap">
				<label class="uplinkportal-email-method">
					<input
						type="radio"
						name="email_delivery_method"
						value="wordpress_mail"
						<?php checked( 'wordpress_mail', (string) $defaults['email_delivery_method'] ); ?>
					/>
					<span>
						<strong><?php echo esc_html__( 'WordPress Mail', 'uplinkportal' ); ?></strong>
						<small><?php echo esc_html__( 'Use your site hosting mail transport.', 'uplinkportal' ); ?></small>
					</span>
				</label>
				<label class="uplinkportal-email-method <?php echo $smtp_plugin_available ? '' : 'is-disabled'; ?>">
					<input
						type="radio"
						name="email_delivery_method"
						value="smtp_plugin"
						<?php checked( 'smtp_plugin', (string) $defaults['email_delivery_method'] ); ?>
						<?php disabled( ! $smtp_plugin_available ); ?>
					/>
					<span>
						<strong><?php echo esc_html__( 'Installed SMTP Plugin', 'uplinkportal' ); ?></strong>
						<small>
							<?php
							echo esc_html(
								$smtp_plugin_available
									? __( 'A compatible SMTP plugin was detected in this WordPress environment.', 'uplinkportal' )
									: __( 'No compatible SMTP plugin detected.', 'uplinkportal' )
							);
							?>
						</small>
					</span>
				</label>
				<label class="uplinkportal-email-method">
					<input
						type="radio"
						name="email_delivery_method"
						value="custom_smtp"
						<?php checked( 'custom_smtp', (string) $defaults['email_delivery_method'] ); ?>
					/>
					<span>
						<strong><?php echo esc_html__( 'Custom SMTP Credentials', 'uplinkportal' ); ?></strong>
						<small><?php echo esc_html__( 'Store and use dedicated SMTP credentials for portal notifications.', 'uplinkportal' ); ?></small>
					</span>
				</label>
			</div>

			<div class="uplinkportal-custom-smtp-fields" data-uplinkportal-custom-smtp <?php echo $show_custom_smtp ? '' : 'hidden'; ?>>
				<div class="uplinkportal-customization-grid">
					<p>
						<label for="uplinkportal-smtp-host"><?php echo esc_html__( 'SMTP Host', 'uplinkportal' ); ?></label>
						<input
							type="text"
							class="regular-text"
							id="uplinkportal-smtp-host"
							name="smtp_host"
							value="<?php echo esc_attr( (string) $defaults['smtp_host'] ); ?>"
						/>
					</p>
					<p>
						<label for="uplinkportal-smtp-port"><?php echo esc_html__( 'SMTP Port', 'uplinkportal' ); ?></label>
						<input
							type="number"
							class="small-text"
							id="uplinkportal-smtp-port"
							name="smtp_port"
							min="1"
							value="<?php echo esc_attr( (string) $defaults['smtp_port'] ); ?>"
						/>
					</p>
					<p>
						<label for="uplinkportal-smtp-username"><?php echo esc_html__( 'SMTP Username', 'uplinkportal' ); ?></label>
						<input
							type="text"
							class="regular-text"
							id="uplinkportal-smtp-username"
							name="smtp_username"
							value="<?php echo esc_attr( (string) $defaults['smtp_username'] ); ?>"
						/>
					</p>
					<p>
						<label for="uplinkportal-smtp-password"><?php echo esc_html__( 'SMTP Password', 'uplinkportal' ); ?></label>
						<input
							type="password"
							class="regular-text"
							id="uplinkportal-smtp-password"
							name="smtp_password"
							value="<?php echo esc_attr( (string) $defaults['smtp_password'] ); ?>"
							autocomplete="new-password"
						/>
					</p>
					<p>
						<label for="uplinkportal-smtp-from"><?php echo esc_html__( 'SMTP From Email', 'uplinkportal' ); ?></label>
						<input
							type="email"
							class="regular-text"
							id="uplinkportal-smtp-from"
							name="smtp_from_email"
							value="<?php echo esc_attr( (string) $defaults['smtp_from_email'] ); ?>"
						/>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders an icon-first save button for a section within the form.
	 *
	 * @param string $section_key Section key (used as submit button value).
	 * @param string $label       Accessible label for the button.
	 * @return void
	 */
	private function render_section_save_button( string $section_key, string $label ): void {
		?>
		<button
			type="submit"
			class="uplinkportal-icon-action uplinkportal-icon-action--save uplinkportal-section-save"
			name="customization_section"
			value="<?php echo esc_attr( $section_key ); ?>"
			title="<?php echo esc_attr( $label ); ?>"
			aria-label="<?php echo esc_attr( $label ); ?>"
		>
			<span class="dashicons dashicons-saved" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
		</button>
		<?php
	}
}
