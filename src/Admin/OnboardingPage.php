<?php
/**
 * First-run onboarding workflow.
 *
 * @package UplinkPortal\Admin
 */

namespace UplinkPortal\Admin;

use UplinkPortal\Core\Security\CapabilityManager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles onboarding steps for first activation.
 */
class OnboardingPage {
	/**
	 * Onboarding menu slug.
	 *
	 * @var string
	 */
	private const ONBOARDING_SLUG = 'uplinkportal-onboarding';

	/**
	 * Data option key.
	 *
	 * @var string
	 */
	private const ONBOARDING_OPTION = 'uplinkportal_onboarding';

	/**
	 * First-run requirement flag option key.
	 *
	 * @var string
	 */
	private const ONBOARDING_REQUIRED_OPTION = 'uplinkportal_onboarding_required';

	/**
	 * Saved onboarding step progress option key.
	 *
	 * @var string
	 */
	private const ONBOARDING_PROGRESS_OPTION = 'uplinkportal_onboarding_progress_step';

	/**
	 * Plugin settings option key.
	 *
	 * @var string
	 */
	private const SETTINGS_OPTION = 'uplinkportal_settings';

	/**
	 * Maximum onboarding step.
	 *
	 * @var int
	 */
	private const MAX_STEP = 8;

	/**
	 * Service modules managed by onboarding.
	 *
	 * @var array<int,string>
	 */
	private const SERVICE_MODULE_KEYS = array(
		'finance',
		'it_support',
		'projects',
	);

	/**
	 * Registers onboarding submenu under UplinkPortal.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			null,
			__( 'UplinkPortal Onboarding', 'uplinkportal' ),
			__( 'Onboarding', 'uplinkportal' ),
			'msp_manage',
			self::ONBOARDING_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Redirects first-time administrators to onboarding.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_onboarding(): void {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! current_user_can( 'msp_manage' ) ) {
			return;
		}

		$required = (bool) get_option( self::ONBOARDING_REQUIRED_OPTION, false );
		if ( ! $required ) {
			return;
		}

		if ( ! $this->is_uplinkportal_admin_page() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing state from query args.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( self::ONBOARDING_SLUG === $page ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::ONBOARDING_SLUG,
					'step' => $this->get_saved_progress_step(),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueues onboarding-only assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::ONBOARDING_SLUG ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'uplinkportal-admin-onboarding',
			uplinkportal_js_url( 'assets/js/admin-onboarding.js' ),
			array( 'jquery' ),
			uplinkportal_js_ver( 'assets/js/admin-onboarding.js' ),
			true
		);
		wp_localize_script(
			'uplinkportal-admin-onboarding',
			'UplinkPortalOnboarding',
			array(
				'title'  => __( 'Select Company Logo', 'uplinkportal' ),
				'button' => __( 'Use This Logo', 'uplinkportal' ),
			)
		);
		wp_enqueue_script(
			'uplinkportal-admin-modules',
			uplinkportal_js_url( 'assets/js/admin-modules.js' ),
			array(),
			uplinkportal_js_ver( 'assets/js/admin-modules.js' ),
			true
		);
	}

	/**
	 * Renders onboarding page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'msp_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access onboarding.', 'uplinkportal' ) );
		}

		$data = $this->get_onboarding_data();
		$step = $this->get_active_step();
		?>
		<div class="wrap uplinkportal-admin uplinkportal-onboarding" data-current-step="<?php echo esc_attr( (string) $step ); ?>" data-max-step="<?php echo esc_attr( (string) self::MAX_STEP ); ?>">
			<header class="uplinkportal-onboarding-header">
				<div>
					<h1><?php echo esc_html__( 'UplinkPortal Onboarding', 'uplinkportal' ); ?></h1>
					<p><?php echo esc_html__( 'Confirm your business profile details before using the portal.', 'uplinkportal' ); ?></p>
				</div>
			</header>
			<?php $this->render_progress_indicator( $step ); ?>
			<?php $this->render_status_notice(); ?>
			<?php $this->render_step_navigation( $step ); ?>
			<?php
			switch ( $step ) {
				case 1:
					$this->render_step_one( $data );
					break;
				case 2:
					$this->render_step_two( $data );
					break;
				case 3:
					$this->render_step_three( $data );
					break;
				case 4:
					$this->render_step_four( $data );
					break;
				case 5:
					$this->render_step_five( $data );
					break;
				case 6:
					$this->render_step_six( $data );
					break;
				case 7:
					$this->render_step_seven( $data );
					break;
				case 8:
				default:
					$this->render_step_eight( $data );
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Handles step one submission.
	 *
	 * @return void
	 */
	public function handle_step_one(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_one', 'uplinkportal_onboarding_nonce' );

		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['company_name'] ) ) : '';
		$logo_id      = isset( $_POST['company_logo_id'] ) ? absint( wp_unslash( (string) $_POST['company_logo_id'] ) ) : 0;
		$confirmed    = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( '' === $company_name || 1 !== $confirmed ) {
			$this->redirect_with_status( 1, 'error_required' );
		}

		if ( $logo_id > 0 ) {
			$logo_validation = $this->validate_logo_attachment( $logo_id );
			if ( true !== $logo_validation ) {
				$this->redirect_with_status( 1, (string) $logo_validation );
			}
		}

		$data                         = $this->get_onboarding_data();
		$data['company_name']         = $company_name;
		$data['company_logo_id']      = $logo_id;
		$data['onboarding_completed'] = false;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->set_saved_progress_step( 2 );
		$this->redirect_with_status( 2, 'saved' );
	}

	/**
	 * Handles step two submission.
	 *
	 * @return void
	 */
	public function handle_step_two(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_two', 'uplinkportal_onboarding_nonce' );

		$email     = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['support_email'] ) ) : '';
		$phone     = isset( $_POST['support_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['support_phone'] ) ) : '';
		$address   = isset( $_POST['office_address'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['office_address'] ) ) : '';
		$confirmed = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( '' === $email || ! is_email( $email ) || 1 !== $confirmed ) {
			$this->redirect_with_status( 2, 'error_required' );
		}

		$data                   = $this->get_onboarding_data();
		$data['support_email']  = $email;
		$data['support_phone']  = $phone;
		$data['office_address'] = $address;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->set_saved_progress_step( 3 );
		$this->redirect_with_status( 3, 'saved' );
	}

	/**
	 * Handles step three submission.
	 *
	 * @return void
	 */
	public function handle_step_three(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_three', 'uplinkportal_onboarding_nonce' );

		$hours     = isset( $_POST['operating_hours'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['operating_hours'] ) ) : '';
		$confirmed = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( '' === $hours || 1 !== $confirmed ) {
			$this->redirect_with_status( 3, 'error_required' );
		}

		$data                    = $this->get_onboarding_data();
		$data['operating_hours'] = $hours;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->set_saved_progress_step( 4 );
		$this->redirect_with_status( 4, 'saved' );
	}

	/**
	 * Handles step four submission.
	 *
	 * @return void
	 */
	public function handle_step_four(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_four', 'uplinkportal_onboarding_nonce' );

		$access_policy       = isset( $_POST['portal_access_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['portal_access_policy'] ) ) : '';
		$registration_policy = isset( $_POST['client_registration_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['client_registration_policy'] ) ) : '';
		$confirmed           = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		$allowed_access_policies = array( 'wp_login_required', 'role_restricted', 'invite_only' );
		$allowed_reg_policies    = array( 'invite_only', 'admin_approval', 'open_registration' );

		if ( ! in_array( $access_policy, $allowed_access_policies, true ) || ! in_array( $registration_policy, $allowed_reg_policies, true ) || 1 !== $confirmed ) {
			$this->redirect_with_status( 4, 'error_required' );
		}

		$data                               = $this->get_onboarding_data();
		$data['portal_access_policy']       = $access_policy;
		$data['client_registration_policy'] = $registration_policy;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->set_saved_progress_step( 5 );
		$this->redirect_with_status( 5, 'saved' );
	}

	/**
	 * Handles step five submission.
	 *
	 * @return void
	 */
	public function handle_step_five(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_five', 'uplinkportal_onboarding_nonce' );

		$posted_modules = isset( $_POST['service_modules'] ) && is_array( $_POST['service_modules'] )
			? wp_unslash( $_POST['service_modules'] )
			: array();
		$confirmed      = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( 1 !== $confirmed ) {
			$this->redirect_with_status( 5, 'error_required' );
		}

		$module_map = array_fill_keys( self::SERVICE_MODULE_KEYS, 0 );
		foreach ( self::SERVICE_MODULE_KEYS as $module_key ) {
			$module_map[ $module_key ] = ! empty( $posted_modules[ $module_key ] ) ? 1 : 0;
		}

		$data                    = $this->get_onboarding_data();
		$data['service_modules'] = $module_map;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->save_service_modules_to_settings( $module_map );
		$this->set_saved_progress_step( 6 );
		$this->redirect_with_status( 6, 'saved' );
	}

	/**
	 * Handles step six submission.
	 *
	 * @return void
	 */
	public function handle_step_six(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_six', 'uplinkportal_onboarding_nonce' );

		$sender_name    = isset( $_POST['notification_sender_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['notification_sender_name'] ) ) : '';
		$sender_email   = isset( $_POST['notification_sender_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['notification_sender_email'] ) ) : '';
		$reply_to_email = isset( $_POST['notification_reply_to_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['notification_reply_to_email'] ) ) : '';
		$email_method   = isset( $_POST['email_delivery_method'] ) ? sanitize_key( wp_unslash( (string) $_POST['email_delivery_method'] ) ) : 'wordpress_mail';
		$smtp_host      = isset( $_POST['smtp_host'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_host'] ) ) : '';
		$smtp_port      = isset( $_POST['smtp_port'] ) ? absint( wp_unslash( (string) $_POST['smtp_port'] ) ) : 0;
		$smtp_username  = isset( $_POST['smtp_username'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_username'] ) ) : '';
		$smtp_password  = isset( $_POST['smtp_password'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['smtp_password'] ) ) : '';
		$smtp_from      = isset( $_POST['smtp_from_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['smtp_from_email'] ) ) : '';
		$smtp_encrypt   = isset( $_POST['smtp_encryption'] ) ? sanitize_key( wp_unslash( (string) $_POST['smtp_encryption'] ) ) : 'tls';
		$send_test      = isset( $_POST['send_test_email'] ) ? absint( wp_unslash( (string) $_POST['send_test_email'] ) ) : 0;
		$confirmed      = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( '' === $sender_name || '' === $sender_email || ! is_email( $sender_email ) || 1 !== $confirmed ) {
			$this->redirect_with_status( 6, 'error_required' );
		}

		if ( '' !== $reply_to_email && ! is_email( $reply_to_email ) ) {
			$this->redirect_with_status( 6, 'error_required' );
		}

		$allowed_methods = array( 'wordpress_mail', 'smtp_plugin', 'custom_smtp' );
		if ( ! in_array( $email_method, $allowed_methods, true ) ) {
			$this->redirect_with_status( 6, 'error_required' );
		}

		if ( 'custom_smtp' === $email_method ) {
			if ( '' === $smtp_host || $smtp_port <= 0 || '' === $smtp_username || '' === $smtp_password || '' === $smtp_from || ! is_email( $smtp_from ) ) {
				$this->redirect_with_status( 6, 'error_required' );
			}
			if ( ! in_array( $smtp_encrypt, array( 'none', 'tls', 'ssl' ), true ) ) {
				$this->redirect_with_status( 6, 'error_required' );
			}
		}

		$data                                = $this->get_onboarding_data();
		$data['notification_sender_name']    = $sender_name;
		$data['notification_sender_email']   = $sender_email;
		$data['notification_reply_to_email'] = $reply_to_email;
		$data['email_delivery_method']       = $email_method;
		$data['smtp_host']                   = $smtp_host;
		$data['smtp_port']                   = $smtp_port;
		$data['smtp_username']               = $smtp_username;
		$data['smtp_password']               = $smtp_password;
		$data['smtp_from_email']             = $smtp_from;
		$data['smtp_encryption']             = $smtp_encrypt;

		update_option( self::ONBOARDING_OPTION, $data, false );

		$status = 'saved';
		if ( 1 === $send_test ) {
			$status = $this->send_test_email( $sender_name, $sender_email, $reply_to_email ) ? 'saved' : 'warning_email_test_failed';
		}

		$this->set_saved_progress_step( 7 );
		$this->redirect_with_status( 7, $status );
	}

	/**
	 * Handles step seven submission.
	 *
	 * @return void
	 */
	public function handle_step_seven(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_seven', 'uplinkportal_onboarding_nonce' );

		$brand_color     = isset( $_POST['brand_primary_color'] ) ? sanitize_hex_color( (string) wp_unslash( $_POST['brand_primary_color'] ) ) : '';
		$welcome_message = isset( $_POST['portal_welcome_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['portal_welcome_message'] ) ) : '';
		$footer_note     = isset( $_POST['portal_footer_note'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['portal_footer_note'] ) ) : '';
		$confirmed       = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( '' === $brand_color || '' === $welcome_message || 1 !== $confirmed ) {
			$this->redirect_with_status( 7, 'error_required' );
		}

		$data                           = $this->get_onboarding_data();
		$data['brand_primary_color']    = $brand_color;
		$data['portal_welcome_message'] = $welcome_message;
		$data['portal_footer_note']     = $footer_note;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->set_saved_progress_step( 8 );
		$this->redirect_with_status( 8, 'saved' );
	}

	/**
	 * Handles step eight submission.
	 *
	 * @return void
	 */
	public function handle_step_eight(): void {
		$this->ensure_onboarding_permissions();
		check_admin_referer( 'uplinkportal_onboarding_step_eight', 'uplinkportal_onboarding_nonce' );

		$enforce_rest_nonce  = isset( $_POST['enforce_rest_nonce'] ) ? absint( wp_unslash( (string) $_POST['enforce_rest_nonce'] ) ) : 0;
		$resync_roles_now    = isset( $_POST['resync_roles_now'] ) ? absint( wp_unslash( (string) $_POST['resync_roles_now'] ) ) : 0;
		$show_security_audit = isset( $_POST['show_security_audit_log'] ) ? absint( wp_unslash( (string) $_POST['show_security_audit_log'] ) ) : 0;
		$confirmed           = isset( $_POST['confirm_step'] ) ? absint( wp_unslash( (string) $_POST['confirm_step'] ) ) : 0;

		if ( 1 !== $confirmed ) {
			$this->redirect_with_status( 8, 'error_required' );
		}

		if ( 1 === $resync_roles_now ) {
			CapabilityManager::manual_resync_roles();
		}

		$data                            = $this->get_onboarding_data();
		$data['enforce_rest_nonce']      = 1 === $enforce_rest_nonce;
		$data['show_security_audit_log'] = 1 === $show_security_audit;
		$data['onboarding_completed']    = true;

		update_option( self::ONBOARDING_OPTION, $data, false );
		$this->save_security_settings( 1 === $enforce_rest_nonce, 1 === $show_security_audit );
		update_option( self::ONBOARDING_REQUIRED_OPTION, 0, false );
		$this->set_saved_progress_step( self::MAX_STEP );

		$redirect = add_query_arg(
			array(
				'page'                    => 'uplinkportal',
				'uplinkportal_onboarding' => 'completed',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Renders onboarding step list.
	 *
	 * @param int $current_step Current step.
	 * @return void
	 */
	private function render_step_navigation( int $current_step ): void {
		$labels = array(
			1 => __( 'Company Profile', 'uplinkportal' ),
			2 => __( 'Support Contact', 'uplinkportal' ),
			3 => __( 'Operating Hours', 'uplinkportal' ),
			4 => __( 'Access Policy', 'uplinkportal' ),
			5 => __( 'Module Setup', 'uplinkportal' ),
			6 => __( 'Notifications', 'uplinkportal' ),
			7 => __( 'Branding', 'uplinkportal' ),
			8 => __( 'Security Baseline', 'uplinkportal' ),
		);
		?>
		<ol class="uplinkportal-onboarding-steps">
			<?php foreach ( $labels as $step => $label ) : ?>
				<li class="<?php echo esc_attr( $step === $current_step ? 'is-current' : '' ); ?>">
					<span class="uplinkportal-onboarding-step-index"><?php echo esc_html( (string) $step ); ?></span>
					<span class="uplinkportal-onboarding-step-label"><?php echo esc_html( $label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Renders compact progress indicator.
	 *
	 * @param int $step Current step.
	 * @return void
	 */
	private function render_progress_indicator( int $step ): void {
		$progress_percent = (int) round( ( $step / self::MAX_STEP ) * 100 );
		?>
		<div class="uplinkportal-onboarding-progress" role="status" aria-live="polite">
			<div class="uplinkportal-onboarding-progress-meta">
				<strong>
					<?php
					/* translators: 1: current onboarding step number, 2: total steps. */
					echo esc_html( sprintf( __( 'Step %1$d of %2$d', 'uplinkportal' ), $step, self::MAX_STEP ) );
					?>
				</strong>
				<span>
					<?php
					/* translators: %d: progress percentage. */
					echo esc_html( sprintf( __( '%d%% complete', 'uplinkportal' ), $progress_percent ) );
					?>
				</span>
			</div>
			<div class="uplinkportal-onboarding-progress-track">
				<span class="uplinkportal-onboarding-progress-fill" style="width: <?php echo esc_attr( (string) $progress_percent ); ?>%;"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders step one form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_one( array $data ): void {
		$logo_id  = isset( $data['company_logo_id'] ) ? absint( $data['company_logo_id'] ) : 0;
		$logo_url = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_one', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_one" />

			<h2><?php echo esc_html__( 'Step 1: Company Profile', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'We imported the company name from your WordPress site title. Adding a logo is optional.', 'uplinkportal' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-company-name"><?php echo esc_html__( 'Company Name', 'uplinkportal' ); ?> *</label></th>
					<td>
						<input type="text" class="regular-text" id="uplinkportal-company-name" name="company_name" required value="<?php echo esc_attr( (string) $data['company_name'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Company Logo', 'uplinkportal' ); ?></th>
					<td>
						<input type="hidden" id="uplinkportal-company-logo-id" name="company_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
						<button type="button" class="button" id="uplinkportal-select-logo"><?php echo esc_html__( 'Select Logo', 'uplinkportal' ); ?></button>
						<p class="description"><?php echo esc_html__( 'Optional: if selected, use under 200KB, max 200x200 px, PNG or SVG.', 'uplinkportal' ); ?></p>
						<div id="uplinkportal-logo-preview">
							<?php if ( ! empty( $logo_url ) ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr__( 'Selected company logo', 'uplinkportal' ); ?>" style="max-width:200px;height:auto;" />
							<?php endif; ?>
						</div>
					</td>
				</tr>
			</table>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm this information is correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 1, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step two form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_two( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_two', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_two" />

			<h2><?php echo esc_html__( 'Step 2: Support Contact', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'We imported the support email from your WordPress admin email when available.', 'uplinkportal' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-support-email"><?php echo esc_html__( 'Main Support/Contact Email', 'uplinkportal' ); ?> *</label></th>
					<td><input type="email" class="regular-text" id="uplinkportal-support-email" name="support_email" required value="<?php echo esc_attr( (string) $data['support_email'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-support-phone"><?php echo esc_html__( 'Phone Number', 'uplinkportal' ); ?></label></th>
					<td><input type="text" class="regular-text" id="uplinkportal-support-phone" name="support_phone" value="<?php echo esc_attr( (string) $data['support_phone'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-office-address"><?php echo esc_html__( 'Main Office Address', 'uplinkportal' ); ?></label></th>
					<td><textarea class="large-text" id="uplinkportal-office-address" name="office_address" rows="3"><?php echo esc_textarea( (string) $data['office_address'] ); ?></textarea></td>
				</tr>
			</table>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm this information is correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 2, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step three form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_three( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_three', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_three" />

			<h2><?php echo esc_html__( 'Step 3: Operating Hours', 'uplinkportal' ); ?></h2>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s timezone label. */
						__( 'Your site timezone is currently set to: %s. Confirm operating hours for client-facing support.', 'uplinkportal' ),
						(string) wp_timezone_string()
					)
				);
				?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-operating-hours"><?php echo esc_html__( 'Operating Hours', 'uplinkportal' ); ?></label></th>
					<td><textarea class="large-text" id="uplinkportal-operating-hours" name="operating_hours" rows="4" required><?php echo esc_textarea( (string) $data['operating_hours'] ); ?></textarea></td>
				</tr>
			</table>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm this information is correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 3, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step four form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_four( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_four', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_four" />

			<h2><?php echo esc_html__( 'Step 4: Client Portal Access Policy', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'These defaults control how clients can access the portal. You can refine this later in Configuration.', 'uplinkportal' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-portal-access-policy"><?php echo esc_html__( 'Portal Access', 'uplinkportal' ); ?> *</label></th>
					<td>
						<select id="uplinkportal-portal-access-policy" name="portal_access_policy" required>
							<option value="wp_login_required" <?php selected( 'wp_login_required', (string) $data['portal_access_policy'] ); ?>><?php echo esc_html__( 'WordPress login required', 'uplinkportal' ); ?></option>
							<option value="role_restricted" <?php selected( 'role_restricted', (string) $data['portal_access_policy'] ); ?>><?php echo esc_html__( 'Only approved role groups', 'uplinkportal' ); ?></option>
							<option value="invite_only" <?php selected( 'invite_only', (string) $data['portal_access_policy'] ); ?>><?php echo esc_html__( 'Invite-only access', 'uplinkportal' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-client-registration-policy"><?php echo esc_html__( 'Client Registration', 'uplinkportal' ); ?> *</label></th>
					<td>
						<select id="uplinkportal-client-registration-policy" name="client_registration_policy" required>
							<option value="invite_only" <?php selected( 'invite_only', (string) $data['client_registration_policy'] ); ?>><?php echo esc_html__( 'Invite-only', 'uplinkportal' ); ?></option>
							<option value="admin_approval" <?php selected( 'admin_approval', (string) $data['client_registration_policy'] ); ?>><?php echo esc_html__( 'Requires admin approval', 'uplinkportal' ); ?></option>
							<option value="open_registration" <?php selected( 'open_registration', (string) $data['client_registration_policy'] ); ?>><?php echo esc_html__( 'Open registration', 'uplinkportal' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm these access defaults are correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 4, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step five form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_five( array $data ): void {
		$service_modules = isset( $data['service_modules'] ) && is_array( $data['service_modules'] )
			? $data['service_modules']
			: array_fill_keys( self::SERVICE_MODULE_KEYS, 0 );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_five', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_five" />

			<h2><?php echo esc_html__( 'Step 5: Module Activation Wizard', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Choose which core services you plan to offer first. You can change these later.', 'uplinkportal' ); ?></p>

			<div class="uplinkportal-module-toggle">
				<label>
					<input type="checkbox" class="uplinkportal-module-checkbox" data-module-key="finance" name="service_modules[finance]" value="1" <?php checked( ! empty( $service_modules['finance'] ) ); ?> />
					<?php echo esc_html__( 'Finance', 'uplinkportal' ); ?>
				</label>
				<div class="uplinkportal-module-required-fields" data-module-fields="finance" hidden>
					<p><strong><?php echo esc_html__( 'Required before launch:', 'uplinkportal' ); ?></strong></p>
					<p><?php echo esc_html__( 'Connect billing provider and verify invoice/customer sync fields before launch.', 'uplinkportal' ); ?></p>
				</div>
			</div>

			<div class="uplinkportal-module-toggle">
				<label>
					<input type="checkbox" class="uplinkportal-module-checkbox" data-module-key="it_support" name="service_modules[it_support]" value="1" <?php checked( ! empty( $service_modules['it_support'] ) ); ?> />
					<?php echo esc_html__( 'IT Support', 'uplinkportal' ); ?>
				</label>
				<div class="uplinkportal-module-required-fields" data-module-fields="it_support" hidden>
					<p><strong><?php echo esc_html__( 'Required before launch:', 'uplinkportal' ); ?></strong></p>
					<p><?php echo esc_html__( 'Connect helpdesk provider and define ticket workflow rules before launch.', 'uplinkportal' ); ?></p>
				</div>
			</div>

			<div class="uplinkportal-module-toggle">
				<label>
					<input type="checkbox" class="uplinkportal-module-checkbox" data-module-key="projects" name="service_modules[projects]" value="1" <?php checked( ! empty( $service_modules['projects'] ) ); ?> />
					<?php echo esc_html__( 'Projects', 'uplinkportal' ); ?>
				</label>
				<div class="uplinkportal-module-required-fields" data-module-fields="projects" hidden>
					<p><strong><?php echo esc_html__( 'Required before launch:', 'uplinkportal' ); ?></strong></p>
					<p><?php echo esc_html__( 'Connect project system and map boards/tasks for clients before launch.', 'uplinkportal' ); ?></p>
				</div>
			</div>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I understand these modules must be configured before client launch.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 5, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step six form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_six( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_six', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_six" />

			<h2><?php echo esc_html__( 'Step 6: Email Notification Setup', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Set your default sender identity for client notifications.', 'uplinkportal' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-notification-sender-name"><?php echo esc_html__( 'Sender Name', 'uplinkportal' ); ?> *</label></th>
					<td><input type="text" class="regular-text" id="uplinkportal-notification-sender-name" name="notification_sender_name" required value="<?php echo esc_attr( (string) $data['notification_sender_name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-notification-sender-email"><?php echo esc_html__( 'Sender Email', 'uplinkportal' ); ?> *</label></th>
					<td><input type="email" class="regular-text" id="uplinkportal-notification-sender-email" name="notification_sender_email" required value="<?php echo esc_attr( (string) $data['notification_sender_email'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-notification-reply-email"><?php echo esc_html__( 'Reply-To Email', 'uplinkportal' ); ?></label></th>
					<td><input type="email" class="regular-text" id="uplinkportal-notification-reply-email" name="notification_reply_to_email" value="<?php echo esc_attr( (string) $data['notification_reply_to_email'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-email-delivery-method"><?php echo esc_html__( 'Email Delivery Method', 'uplinkportal' ); ?> *</label></th>
					<td>
						<select id="uplinkportal-email-delivery-method" name="email_delivery_method" data-uplinkportal-email-delivery required>
							<option value="wordpress_mail" <?php selected( 'wordpress_mail', (string) $data['email_delivery_method'] ); ?>><?php echo esc_html__( 'Use WordPress site email sending', 'uplinkportal' ); ?></option>
							<option value="smtp_plugin" <?php selected( 'smtp_plugin', (string) $data['email_delivery_method'] ); ?>><?php echo esc_html__( 'Use installed SMTP plugin (if available)', 'uplinkportal' ); ?></option>
							<option value="custom_smtp" <?php selected( 'custom_smtp', (string) $data['email_delivery_method'] ); ?>><?php echo esc_html__( 'Enter SMTP credentials', 'uplinkportal' ); ?></option>
						</select>
						<p class="description"><?php echo esc_html__( 'Choose how email should be sent from this portal.', 'uplinkportal' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="uplinkportal-module-required-fields" data-uplinkportal-email-fields="custom_smtp" hidden>
				<h3><?php echo esc_html__( 'SMTP Credentials', 'uplinkportal' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-host"><?php echo esc_html__( 'SMTP Host', 'uplinkportal' ); ?></label></th>
						<td><input type="text" class="regular-text" id="uplinkportal-smtp-host" name="smtp_host" value="<?php echo esc_attr( (string) $data['smtp_host'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-port"><?php echo esc_html__( 'SMTP Port', 'uplinkportal' ); ?></label></th>
						<td><input type="number" class="small-text" id="uplinkportal-smtp-port" name="smtp_port" min="1" value="<?php echo esc_attr( (string) $data['smtp_port'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-username"><?php echo esc_html__( 'SMTP Username', 'uplinkportal' ); ?></label></th>
						<td><input type="text" class="regular-text" id="uplinkportal-smtp-username" name="smtp_username" value="<?php echo esc_attr( (string) $data['smtp_username'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-password"><?php echo esc_html__( 'SMTP Password', 'uplinkportal' ); ?></label></th>
						<td><input type="password" class="regular-text" id="uplinkportal-smtp-password" name="smtp_password" value="<?php echo esc_attr( (string) $data['smtp_password'] ); ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-from-email"><?php echo esc_html__( 'SMTP From Email', 'uplinkportal' ); ?></label></th>
						<td><input type="email" class="regular-text" id="uplinkportal-smtp-from-email" name="smtp_from_email" value="<?php echo esc_attr( (string) $data['smtp_from_email'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="uplinkportal-smtp-encryption"><?php echo esc_html__( 'Encryption', 'uplinkportal' ); ?></label></th>
						<td>
							<select id="uplinkportal-smtp-encryption" name="smtp_encryption">
								<option value="tls" <?php selected( 'tls', (string) $data['smtp_encryption'] ); ?>><?php echo esc_html__( 'TLS', 'uplinkportal' ); ?></option>
								<option value="ssl" <?php selected( 'ssl', (string) $data['smtp_encryption'] ); ?>><?php echo esc_html__( 'SSL', 'uplinkportal' ); ?></option>
								<option value="none" <?php selected( 'none', (string) $data['smtp_encryption'] ); ?>><?php echo esc_html__( 'None', 'uplinkportal' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p class="description"><?php echo esc_html__( 'Only enter SMTP credentials if you are self-hosting your mail relay.', 'uplinkportal' ); ?></p>
			</div>

			<p>
				<label>
					<input type="checkbox" name="send_test_email" value="1" />
					<?php echo esc_html__( 'Send a test email to my current WordPress account email.', 'uplinkportal' ); ?>
				</label>
			</p>
			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm this notification setup is correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 6, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step seven form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_seven( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_seven', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_seven" />

			<h2><?php echo esc_html__( 'Step 7: Branding and Workspace Defaults', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Set a simple visual baseline for your client workspace.', 'uplinkportal' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="uplinkportal-brand-primary-color"><?php echo esc_html__( 'Primary Brand Color', 'uplinkportal' ); ?> *</label></th>
					<td><input type="color" id="uplinkportal-brand-primary-color" name="brand_primary_color" value="<?php echo esc_attr( (string) $data['brand_primary_color'] ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-portal-welcome-message"><?php echo esc_html__( 'Portal Welcome Message', 'uplinkportal' ); ?> *</label></th>
					<td><textarea class="large-text" id="uplinkportal-portal-welcome-message" name="portal_welcome_message" rows="3" required><?php echo esc_textarea( (string) $data['portal_welcome_message'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="uplinkportal-portal-footer-note"><?php echo esc_html__( 'Portal Footer Note', 'uplinkportal' ); ?></label></th>
					<td><textarea class="large-text" id="uplinkportal-portal-footer-note" name="portal_footer_note" rows="2"><?php echo esc_textarea( (string) $data['portal_footer_note'] ); ?></textarea></td>
				</tr>
			</table>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm these branding defaults are correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 7, __( 'Save and Continue', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders step eight form.
	 *
	 * @param array<string,mixed> $data Existing profile data.
	 * @return void
	 */
	private function render_step_eight( array $data ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uplinkportal-admin-card">
			<?php wp_nonce_field( 'uplinkportal_onboarding_step_eight', 'uplinkportal_onboarding_nonce' ); ?>
			<input type="hidden" name="action" value="uplinkportal_onboarding_step_eight" />

			<h2><?php echo esc_html__( 'Step 8: Security Baseline', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Apply baseline security defaults now. These settings can be changed later if needed.', 'uplinkportal' ); ?></p>

			<p>
				<label>
					<input type="checkbox" name="enforce_rest_nonce" value="1" <?php checked( ! empty( $data['enforce_rest_nonce'] ) ); ?> />
					<?php echo esc_html__( 'Require secure session checks for portal API requests.', 'uplinkportal' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="resync_roles_now" value="1" checked="checked" />
					<?php echo esc_html__( 'Resync default roles now to apply the latest capability map.', 'uplinkportal' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="show_security_audit_log" value="1" <?php checked( ! empty( $data['show_security_audit_log'] ) ); ?> />
					<?php echo esc_html__( 'Show the security audit log in Configuration.', 'uplinkportal' ); ?>
				</label>
			</p>

			<label>
				<input type="checkbox" name="confirm_step" value="1" required />
				<?php echo esc_html__( 'I confirm these security defaults are correct.', 'uplinkportal' ); ?>
			</label>
			<?php $this->render_form_actions( 8, __( 'Complete Onboarding', 'uplinkportal' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Returns onboarding profile data with WordPress-imported defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function get_onboarding_data(): array {
		$stored = get_option( self::ONBOARDING_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$timezone = wp_timezone_string();
		if ( '' === $timezone ) {
			$timezone = 'UTC';
		}

		$settings          = $this->get_plugin_settings();
		$default_modules   = array_fill_keys( self::SERVICE_MODULE_KEYS, 0 );
		$settings_modules  = isset( $settings['service_modules'] ) && is_array( $settings['service_modules'] )
			? $settings['service_modules']
			: array();
		$stored_modules    = isset( $stored['service_modules'] ) && is_array( $stored['service_modules'] )
			? $stored['service_modules']
			: $settings_modules;
		$service_modules   = array();
		$company_logo_id   = isset( $stored['company_logo_id'] ) ? absint( $stored['company_logo_id'] ) : 0;
		$support_email     = isset( $stored['support_email'] ) ? sanitize_email( (string) $stored['support_email'] ) : sanitize_email( (string) get_option( 'admin_email', '' ) );
		$notification_from = isset( $settings['notification_sender_email'] ) ? sanitize_email( (string) $settings['notification_sender_email'] ) : $support_email;

		if ( $company_logo_id > 0 && true !== $this->validate_logo_attachment( $company_logo_id ) ) {
			$company_logo_id = 0;
		}

		foreach ( self::SERVICE_MODULE_KEYS as $module_key ) {
			$base_default                   = ! empty( $default_modules[ $module_key ] ) ? 1 : 0;
			$service_modules[ $module_key ] = ! empty( $stored_modules[ $module_key ] ) ? 1 : $base_default;
		}

		return array(
			'company_name'                => isset( $stored['company_name'] ) ? sanitize_text_field( (string) $stored['company_name'] ) : sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'company_logo_id'             => $company_logo_id,
			'support_email'               => $support_email,
			'support_phone'               => isset( $stored['support_phone'] ) ? sanitize_text_field( (string) $stored['support_phone'] ) : '',
			'office_address'              => isset( $stored['office_address'] ) ? sanitize_textarea_field( (string) $stored['office_address'] ) : '',
			/* translators: %s site timezone. */
			'operating_hours'             => isset( $stored['operating_hours'] ) ? sanitize_textarea_field( (string) $stored['operating_hours'] ) : sprintf( __( 'Monday-Friday 9:00 AM - 5:00 PM (%s)', 'uplinkportal' ), $timezone ),
			'portal_access_policy'        => isset( $stored['portal_access_policy'] ) ? sanitize_key( (string) $stored['portal_access_policy'] ) : 'wp_login_required',
			'client_registration_policy'  => isset( $stored['client_registration_policy'] ) ? sanitize_key( (string) $stored['client_registration_policy'] ) : 'invite_only',
			'service_modules'             => $service_modules,
			'notification_sender_name'    => isset( $stored['notification_sender_name'] ) ? sanitize_text_field( (string) $stored['notification_sender_name'] ) : sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'notification_sender_email'   => isset( $stored['notification_sender_email'] ) ? sanitize_email( (string) $stored['notification_sender_email'] ) : $notification_from,
			'notification_reply_to_email' => isset( $stored['notification_reply_to_email'] ) ? sanitize_email( (string) $stored['notification_reply_to_email'] ) : $support_email,
			'email_delivery_method'       => isset( $stored['email_delivery_method'] ) ? sanitize_key( (string) $stored['email_delivery_method'] ) : 'wordpress_mail',
			'smtp_host'                   => isset( $stored['smtp_host'] ) ? sanitize_text_field( (string) $stored['smtp_host'] ) : '',
			'smtp_port'                   => isset( $stored['smtp_port'] ) ? absint( $stored['smtp_port'] ) : 587,
			'smtp_username'               => isset( $stored['smtp_username'] ) ? sanitize_text_field( (string) $stored['smtp_username'] ) : '',
			'smtp_password'               => isset( $stored['smtp_password'] ) ? sanitize_text_field( (string) $stored['smtp_password'] ) : '',
			'smtp_from_email'             => isset( $stored['smtp_from_email'] ) ? sanitize_email( (string) $stored['smtp_from_email'] ) : $support_email,
			'smtp_encryption'             => isset( $stored['smtp_encryption'] ) ? sanitize_key( (string) $stored['smtp_encryption'] ) : 'tls',
			'brand_primary_color'         => isset( $stored['brand_primary_color'] ) ? sanitize_hex_color( (string) $stored['brand_primary_color'] ) : '#1d4ed8',
			/* translators: %s site name. */
			'portal_welcome_message'      => isset( $stored['portal_welcome_message'] ) ? sanitize_textarea_field( (string) $stored['portal_welcome_message'] ) : sprintf( __( 'Welcome to %s Client Workspace.', 'uplinkportal' ), sanitize_text_field( (string) get_bloginfo( 'name' ) ) ),
			'portal_footer_note'          => isset( $stored['portal_footer_note'] ) ? sanitize_textarea_field( (string) $stored['portal_footer_note'] ) : '',
			'enforce_rest_nonce'          => isset( $stored['enforce_rest_nonce'] ) ? (bool) $stored['enforce_rest_nonce'] : true,
			'show_security_audit_log'     => isset( $stored['show_security_audit_log'] ) ? (bool) $stored['show_security_audit_log'] : true,
			'onboarding_completed'        => ! empty( $stored['onboarding_completed'] ),
		);
	}

	/**
	 * Resolves active step from query argument.
	 *
	 * @return int
	 */
	private function get_active_step(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only step state.
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : $this->get_saved_progress_step();
		if ( $step < 1 || $step > self::MAX_STEP ) {
			return $this->get_saved_progress_step();
		}
		return $step;
	}

	/**
	 * Validates selected logo attachment.
	 *
	 * @param int $logo_id Attachment id.
	 * @return true|string
	 */
	private function validate_logo_attachment( int $logo_id ) {
		if ( $logo_id <= 0 ) {
			return 'error_logo_required';
		}

		$mime_type = (string) get_post_mime_type( $logo_id );
		if ( ! in_array( $mime_type, array( 'image/png', 'image/svg+xml' ), true ) ) {
			return 'error_logo_format';
		}

		$file_path = get_attached_file( $logo_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return 'error_logo_missing';
		}

		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > 204800 ) {
			return 'error_logo_size';
		}

		$dimensions = wp_getimagesize( $file_path );
		if ( 'image/png' === $mime_type && ! is_array( $dimensions ) ) {
			return 'error_logo_dimensions';
		}
		if ( is_array( $dimensions ) && ( (int) $dimensions[0] > 200 || (int) $dimensions[1] > 200 ) ) {
			return 'error_logo_dimensions';
		}

		return true;
	}

	/**
	 * Renders status notices from query state.
	 *
	 * @return void
	 */
	private function render_status_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status from query args.
		$status = isset( $_GET['uplinkportal_onboarding'] ) ? sanitize_key( (string) $_GET['uplinkportal_onboarding'] ) : '';

		if ( '' === $status ) {
			return;
		}

		$messages = array(
			'saved'                     => __( 'Step saved. Continue onboarding.', 'uplinkportal' ),
			'completed'                 => __( 'Onboarding completed successfully.', 'uplinkportal' ),
			'warning_email_test_failed' => __( 'Step saved, but the test email could not be delivered.', 'uplinkportal' ),
			'error_required'            => __( 'Please complete all required fields and confirmation checkbox.', 'uplinkportal' ),
			'error_logo_required'       => __( 'Company logo is required in Step 1.', 'uplinkportal' ),
			'error_logo_format'         => __( 'Company logo must be a PNG or SVG file.', 'uplinkportal' ),
			'error_logo_size'           => __( 'Company logo must be under 200KB.', 'uplinkportal' ),
			'error_logo_dimensions'     => __( 'Company logo dimensions cannot exceed 200x200 px.', 'uplinkportal' ),
			'error_logo_missing'        => __( 'Unable to locate the selected logo file.', 'uplinkportal' ),
		);

		$message = isset( $messages[ $status ] ) ? $messages[ $status ] : __( 'Unable to process onboarding request.', 'uplinkportal' );
		$class   = ( 0 === strpos( $status, 'error_' ) ) ? 'notice notice-error' : 'notice notice-success';
		if ( 'warning_email_test_failed' === $status ) {
			$class = 'notice notice-warning';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?> inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Guards onboarding handlers for correct capability.
	 *
	 * @return void
	 */
	private function ensure_onboarding_permissions(): void {
		if ( ! current_user_can( 'msp_manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to complete onboarding.', 'uplinkportal' ) );
		}
	}

	/**
	 * Redirect helper for onboarding step handlers.
	 *
	 * @param int    $step Next/current step.
	 * @param string $status Status key.
	 * @return void
	 */
	private function redirect_with_status( int $step, string $status ): void {
		$redirect = add_query_arg(
			array(
				'page'                    => self::ONBOARDING_SLUG,
				'step'                    => max( 1, min( self::MAX_STEP, $step ) ),
				'uplinkportal_onboarding' => sanitize_key( $status ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Renders step action buttons with Back on steps 2-8.
	 *
	 * @param int    $step Current step.
	 * @param string $primary_label Primary submit button label.
	 * @return void
	 */
	private function render_form_actions( int $step, string $primary_label ): void {
		?>
		<div class="uplinkportal-onboarding-actions">
			<?php if ( $step > 1 ) : ?>
				<a class="button button-secondary uplinkportal-onboarding-back" href="<?php echo esc_url( $this->get_step_url( $step - 1 ) ); ?>">
					<?php echo esc_html__( 'Back', 'uplinkportal' ); ?>
				</a>
			<?php endif; ?>
			<?php submit_button( $primary_label, 'primary', 'submit', false ); ?>
		</div>
		<?php
	}

	/**
	 * Returns onboarding step URL.
	 *
	 * @param int $step Step number.
	 * @return string
	 */
	private function get_step_url( int $step ): string {
		return add_query_arg(
			array(
				'page' => self::ONBOARDING_SLUG,
				'step' => max( 1, min( self::MAX_STEP, $step ) ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Returns saved onboarding progress step.
	 *
	 * @return int
	 */
	private function get_saved_progress_step(): int {
		$step = absint( get_option( self::ONBOARDING_PROGRESS_OPTION, 1 ) );
		if ( $step < 1 || $step > self::MAX_STEP ) {
			return 1;
		}

		return $step;
	}

	/**
	 * Persists onboarding progress step.
	 *
	 * @param int $step Step number.
	 * @return void
	 */
	private function set_saved_progress_step( int $step ): void {
		update_option( self::ONBOARDING_PROGRESS_OPTION, max( 1, min( self::MAX_STEP, $step ) ), false );
	}

	/**
	 * Whether current request is for a UplinkPortal admin page.
	 *
	 * @return bool
	 */
	private function is_uplinkportal_admin_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing state from query args.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		if ( '' === $page ) {
			return false;
		}

		return 0 === strpos( $page, 'uplinkportal' );
	}

	/**
	 * Sends onboarding test email.
	 *
	 * @param string $sender_name Sender name.
	 * @param string $sender_email Sender email.
	 * @param string $reply_to_email Reply-to email.
	 * @return bool
	 */
	private function send_test_email( string $sender_name, string $sender_email, string $reply_to_email ): bool {
		$current_user = wp_get_current_user();
		$recipient    = $current_user instanceof \WP_User ? sanitize_email( (string) $current_user->user_email ) : '';

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return false;
		}

		$headers = array(
			sprintf( 'From: %s <%s>', $sender_name, $sender_email ),
		);

		if ( '' !== $reply_to_email && is_email( $reply_to_email ) ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to_email );
		}

		$subject = __( 'UplinkPortal Onboarding Test Email', 'uplinkportal' );
		$body    = __( 'This is a test email sent during UplinkPortal onboarding.', 'uplinkportal' );

		return (bool) wp_mail( $recipient, $subject, $body, $headers );
	}

	/**
	 * Returns plugin settings array.
	 *
	 * @return array<string,mixed>
	 */
	private function get_plugin_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Persists service module map into plugin settings.
	 *
	 * @param array<string,int> $module_map Module toggle map.
	 * @return void
	 */
	private function save_service_modules_to_settings( array $module_map ): void {
		$settings                    = $this->get_plugin_settings();
		$settings['service_modules'] = array_fill_keys( self::SERVICE_MODULE_KEYS, 0 );

		foreach ( self::SERVICE_MODULE_KEYS as $module_key ) {
			$settings['service_modules'][ $module_key ] = ! empty( $module_map[ $module_key ] ) ? 1 : 0;
		}

		update_option( self::SETTINGS_OPTION, $settings, false );
	}

	/**
	 * Persists security baseline values into plugin settings.
	 *
	 * @param bool $enforce_rest_nonce Enforce REST nonce checks.
	 * @param bool $show_security_audit_log Show audit log in admin.
	 * @return void
	 */
	private function save_security_settings( bool $enforce_rest_nonce, bool $show_security_audit_log ): void {
		$settings                            = $this->get_plugin_settings();
		$settings['strict_rest_nonce']       = $enforce_rest_nonce ? 1 : 0;
		$settings['show_security_audit_log'] = $show_security_audit_log ? 1 : 0;
		update_option( self::SETTINGS_OPTION, $settings, false );
	}
}
