<?php
/**
 * Client portfolio and operations tracking UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders client-portfolio-related admin UI sections.
 */
class ClientPortfolioUi {
	/**
	 * Renders projects and service delivery tracking panel.
	 *
	 * @param array<int,array<string,mixed>> $portfolio_rows Portfolio rows.
	 * @param string                         $portfolio_page_url Client portfolio URL.
	 * @return void
	 */
	public function render_operations_delivery_tracking_panel( array $portfolio_rows, string $portfolio_page_url ): void {
		$total_tickets         = 0;
		$total_active_projects = 0;

		foreach ( $portfolio_rows as $row ) {
			$total_tickets += isset( $row['ticket_count'] ) ? absint( $row['ticket_count'] ) : 0;

			$total_active_projects += isset( $row['active_project_count'] ) ? absint( $row['active_project_count'] ) : 0;
		}

		$sorted_rows = $portfolio_rows;
		usort(
			$sorted_rows,
			static function ( array $left, array $right ): int {
				$left_projects  = isset( $left['active_project_count'] ) ? absint( $left['active_project_count'] ) : 0;
				$right_projects = isset( $right['active_project_count'] ) ? absint( $right['active_project_count'] ) : 0;
				if ( $left_projects === $right_projects ) {
					$left_tickets  = isset( $left['ticket_count'] ) ? absint( $left['ticket_count'] ) : 0;
					$right_tickets = isset( $right['ticket_count'] ) ? absint( $right['ticket_count'] ) : 0;
					return $right_tickets <=> $left_tickets;
				}
				return $right_projects <=> $left_projects;
			}
		);
		$preview_rows = array_slice( $sorted_rows, 0, 6 );
		?>
		<div class="uplinkportal-admin-card">
			<h2><?php echo esc_html__( 'Projects & Service Delivery Tracking', 'uplinkportal' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Monitor active project load and ticket volume by client so coordinators can balance delivery work.', 'uplinkportal' ); ?></p>
			<p>
				<strong><?php echo esc_html__( 'Active Projects:', 'uplinkportal' ); ?></strong> <?php echo esc_html( (string) $total_active_projects ); ?>
				&nbsp;|&nbsp;
				<strong><?php echo esc_html__( 'Total Tickets:', 'uplinkportal' ); ?></strong> <?php echo esc_html( (string) $total_tickets ); ?>
			</p>
			<?php if ( empty( $preview_rows ) ) : ?>
				<p><?php echo esc_html__( 'No client activity data is available yet.', 'uplinkportal' ); ?></p>
			<?php else : ?>
				<div class="uplinkportal-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Client', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Plan', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Active Projects', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Ticket Count', 'uplinkportal' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $preview_rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $row['client_name'] ) ? (string) $row['client_name'] : '' ); ?></td>
									<td><?php echo esc_html( isset( $row['plan_label'] ) ? (string) $row['plan_label'] : '' ); ?></td>
									<td><?php echo esc_html( (string) ( isset( $row['active_project_count'] ) ? absint( $row['active_project_count'] ) : 0 ) ); ?></td>
									<td><?php echo esc_html( (string) ( isset( $row['ticket_count'] ) ? absint( $row['ticket_count'] ) : 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $portfolio_page_url ); ?>">
					<?php echo esc_html__( 'Open Client Portfolio', 'uplinkportal' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders Client Portfolio list table and modal edit flows.
	 *
	 * @param array<int,array<string,mixed>> $rows Portfolio rows.
	 * @param bool                           $backend_ready Backend readiness.
	 * @param string                         $database_settings_url Database settings URL.
	 * @param string                         $portfolio_page_url Client portfolio URL.
	 * @return void
	 */
	public function render_client_portfolio_table( array $rows, bool $backend_ready, string $database_settings_url, string $portfolio_page_url ): void {
		if ( ! $backend_ready ) :
			?>
			<div class="notice notice-warning inline">
				<p>
					<?php echo esc_html__( 'Client edits require a configured backend connection (backend URL + backend service token). Configure this in Modules > Database before saving edits.', 'uplinkportal' ); ?>
					<a href="<?php echo esc_url( $database_settings_url ); ?>"><?php echo esc_html__( 'Open Database settings', 'uplinkportal' ); ?></a>
				</p>
			</div>
			<?php
		endif;
		?>

		<div class="uplinkportal-admin-card">
			<h2><?php echo esc_html__( 'Client Portfolio List', 'uplinkportal' ); ?></h2>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'No client users found yet. Assign users to UplinkPortal client roles to populate this portfolio.', 'uplinkportal' ); ?></p>
			<?php else : ?>
				<div class="uplinkportal-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Client', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Subscribed Plan', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Total Ticket Count', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Active Project Count', 'uplinkportal' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'uplinkportal' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$client_id = isset( $row['client_id'] ) ? absint( $row['client_id'] ) : 0;
								if ( $client_id <= 0 ) {
									continue;
								}
								$modal_id = 'uplinkportal-client-record-edit-' . $client_id;
								$profile  = isset( $row['profile'] ) && is_array( $row['profile'] ) ? $row['profile'] : array();
								?>
							<tr>
								<td>
									<strong><?php echo esc_html( isset( $row['client_name'] ) ? (string) $row['client_name'] : '' ); ?></strong><br />
									<code><?php echo esc_html( isset( $row['user_email'] ) ? (string) $row['user_email'] : '' ); ?></code>
								</td>
								<td><?php echo esc_html( isset( $row['plan_label'] ) ? (string) $row['plan_label'] : '' ); ?></td>
								<td><?php echo esc_html( (string) ( isset( $row['ticket_count'] ) ? absint( $row['ticket_count'] ) : 0 ) ); ?></td>
								<td><?php echo esc_html( (string) ( isset( $row['active_project_count'] ) ? absint( $row['active_project_count'] ) : 0 ) ); ?></td>
								<td>
									<button
										type="button"
										class="button uplinkportal-open-override"
										data-override-target="<?php echo esc_attr( $modal_id ); ?>"
										<?php disabled( ! $backend_ready ); ?>
									>
										<?php echo esc_html__( 'Edit Client', 'uplinkportal' ); ?>
									</button>
									<?php if ( ! $backend_ready ) : ?>
										<p class="description"><?php echo esc_html__( 'Backend connection required.', 'uplinkportal' ); ?></p>
									<?php endif; ?>

									<div id="<?php echo esc_attr( $modal_id ); ?>" class="uplinkportal-override-modal" hidden>
										<div class="uplinkportal-override-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Edit client details', 'uplinkportal' ); ?>">
											<h3><?php echo esc_html__( 'Edit Client', 'uplinkportal' ); ?></h3>
											<p class="description"><?php echo esc_html__( 'Update delivery contact details for this client. Changes require backend integration to be configured.', 'uplinkportal' ); ?></p>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<?php wp_nonce_field( 'uplinkportal_save_client_portfolio', 'uplinkportal_save_client_portfolio_nonce' ); ?>
												<input type="hidden" name="action" value="uplinkportal_save_client_portfolio" />
												<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
												<input type="hidden" name="redirect_to" value="<?php echo esc_url( $portfolio_page_url ); ?>" />

												<p>
													<label for="uplinkportal-client-company-name-<?php echo esc_attr( (string) $client_id ); ?>"><?php echo esc_html__( 'Company Name', 'uplinkportal' ); ?></label><br />
													<input id="uplinkportal-client-company-name-<?php echo esc_attr( (string) $client_id ); ?>" type="text" class="regular-text" name="company_name" value="<?php echo esc_attr( isset( $profile['company_name'] ) ? (string) $profile['company_name'] : '' ); ?>" />
												</p>
												<p>
													<label for="uplinkportal-client-department-<?php echo esc_attr( (string) $client_id ); ?>"><?php echo esc_html__( 'Department', 'uplinkportal' ); ?></label><br />
													<input id="uplinkportal-client-department-<?php echo esc_attr( (string) $client_id ); ?>" type="text" class="regular-text" name="department" value="<?php echo esc_attr( isset( $profile['department'] ) ? (string) $profile['department'] : '' ); ?>" />
												</p>
												<p>
													<label for="uplinkportal-client-title-<?php echo esc_attr( (string) $client_id ); ?>"><?php echo esc_html__( 'Title', 'uplinkportal' ); ?></label><br />
													<input id="uplinkportal-client-title-<?php echo esc_attr( (string) $client_id ); ?>" type="text" class="regular-text" name="title" value="<?php echo esc_attr( isset( $profile['title'] ) ? (string) $profile['title'] : '' ); ?>" />
												</p>
												<p>
													<label for="uplinkportal-client-phone-<?php echo esc_attr( (string) $client_id ); ?>"><?php echo esc_html__( 'Phone Number', 'uplinkportal' ); ?></label><br />
													<input id="uplinkportal-client-phone-<?php echo esc_attr( (string) $client_id ); ?>" type="text" class="regular-text" name="phone_number" value="<?php echo esc_attr( isset( $profile['phone_number'] ) ? (string) $profile['phone_number'] : '' ); ?>" />
												</p>
												<p>
													<label for="uplinkportal-client-email-<?php echo esc_attr( (string) $client_id ); ?>"><?php echo esc_html__( 'Email Address', 'uplinkportal' ); ?></label><br />
													<input id="uplinkportal-client-email-<?php echo esc_attr( (string) $client_id ); ?>" type="email" class="regular-text" name="email_address" value="<?php echo esc_attr( isset( $profile['email_address'] ) ? (string) $profile['email_address'] : '' ); ?>" />
												</p>
												<p>
													<button type="submit" class="button button-primary" <?php disabled( ! $backend_ready ); ?>><?php echo esc_html__( 'Save Client', 'uplinkportal' ); ?></button>
													<button type="button" class="button uplinkportal-close-override"><?php echo esc_html__( 'Cancel', 'uplinkportal' ); ?></button>
												</p>
											</form>
										</div>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
