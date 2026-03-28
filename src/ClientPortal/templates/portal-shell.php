<?php
/**
 * Portal shell template.
 *
 * @package UplinkPortal
 *
 * @var array<string,mixed> $context
 */

defined( 'ABSPATH' ) || exit;

$context = isset( $context ) && is_array( $context ) ? $context : array();

$modules             = isset( $context['modules'] ) && is_array( $context['modules'] ) ? $context['modules'] : array();
$workspace_sections  = isset( $context['workspace_sections'] ) && is_array( $context['workspace_sections'] ) ? $context['workspace_sections'] : array();
$client_documents    = isset( $context['client_documents'] ) && is_array( $context['client_documents'] ) ? $context['client_documents'] : array();
$brand_primary_color = sanitize_hex_color( (string) ( $context['brand_primary_color'] ?? '' ) );
if ( ! is_string( $brand_primary_color ) || '' === $brand_primary_color ) {
	$brand_primary_color = '#1d4ed8';
}

$portal_welcome_message = sanitize_textarea_field( (string) ( $context['portal_welcome_message'] ?? '' ) );
if ( '' === trim( $portal_welcome_message ) ) {
	$portal_welcome_message = __( 'Welcome to your unified MSP portal.', 'uplinkportal' );
}

$portal_footer_note      = sanitize_textarea_field( (string) ( $context['portal_footer_note'] ?? '' ) );
$purchase_request_status = sanitize_key( (string) ( $context['purchase_request_status'] ?? 'unknown' ) );
$request_items           = isset( $context['request_items'] ) && is_array( $context['request_items'] ) ? $context['request_items'] : array();
if ( empty( $request_items ) ) {
	$request_items = array(
		'general' => __( 'General Service Request', 'uplinkportal' ),
	);
}

$normalized_request_items = array();
foreach ( $request_items as $request_key => $request_label ) {
	$key   = sanitize_key( (string) $request_key );
	$label = sanitize_text_field( (string) $request_label );
	if ( '' === $key || '' === $label ) {
		continue;
	}

	$normalized_request_items[ $key ] = $label;
}
$request_items = ! empty( $normalized_request_items ) ? $normalized_request_items : array(
	'general' => __( 'General Service Request', 'uplinkportal' ),
);

$request_redirect = get_permalink();
if ( ! is_string( $request_redirect ) || '' === $request_redirect ) {
	$request_redirect = home_url( '/' );
}
?>
<div
	id="uplinkportal-root"
	class="uplinkportal-shell"
	data-version="<?php echo esc_attr( UPLINKPORTAL_VERSION ); ?>"
	style="<?php echo esc_attr( '--uplinkportal-brand: ' . $brand_primary_color . ';' ); ?>"
>
	<div class="uplinkportal-header">
		<h2><?php echo esc_html__( 'Client Portal', 'uplinkportal' ); ?></h2>
		<p data-uplinkportal-preview-welcome><?php echo esc_html( $portal_welcome_message ); ?></p>
	</div>
	<?php if ( 'submitted' === $purchase_request_status ) : ?>
		<div class="notice notice-success inline">
			<p><?php echo esc_html__( 'Your service request was submitted to the MSP provider team.', 'uplinkportal' ); ?></p>
		</div>
	<?php elseif ( 'error' === $purchase_request_status ) : ?>
		<div class="notice notice-error inline">
			<p><?php echo esc_html__( 'Your service request could not be submitted. Please try again.', 'uplinkportal' ); ?></p>
		</div>
	<?php endif; ?>
	<noscript>
		<div class="notice notice-info inline uplinkportal-noscript-notice">
			<p><?php echo esc_html__( 'JavaScript is disabled. Portal navigation fallback is active and all sections are shown inline.', 'uplinkportal' ); ?></p>
		</div>
	</noscript>

	<div class="uplinkportal-layout">
		<nav class="uplinkportal-sidebar" aria-label="<?php echo esc_attr__( 'Portal sections', 'uplinkportal' ); ?>">
			<ul class="uplinkportal-sections">
				<?php if ( ! empty( $workspace_sections ) ) : ?>
					<?php foreach ( $workspace_sections as $index => $section ) : ?>
						<li class="uplinkportal-sections__item">
							<a
								class="uplinkportal-sections__link <?php echo 0 === $index ? 'is-active' : ''; ?>"
								href="#uplinkportal-section-<?php echo esc_attr( $section['id'] ); ?>"
								aria-current="<?php echo 0 === $index ? 'page' : 'false'; ?>"
							>
								<?php echo esc_html( $section['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				<?php else : ?>
					<li class="uplinkportal-sections__item">
						<span class="uplinkportal-sections__link is-disabled"><?php echo esc_html__( 'No sections available', 'uplinkportal' ); ?></span>
					</li>
				<?php endif; ?>
			</ul>
		</nav>

		<main class="uplinkportal-content">
			<?php if ( ! empty( $workspace_sections ) ) : ?>
				<?php foreach ( $workspace_sections as $index => $section ) : ?>
					<section
						id="uplinkportal-section-<?php echo esc_attr( $section['id'] ); ?>"
						class="uplinkportal-section <?php echo 0 === $index ? 'is-active' : ''; ?>"
						role="region"
						aria-labelledby="uplinkportal-section-title-<?php echo esc_attr( $section['id'] ); ?>"
					>
						<h3 id="uplinkportal-section-title-<?php echo esc_attr( $section['id'] ); ?>"><?php echo esc_html( $section['label'] ); ?></h3>
						<p><?php echo esc_html( $section['description'] ); ?></p>
						<?php if ( 'documents-agreements' === $section['id'] ) : ?>
							<?php if ( ! empty( $client_documents ) ) : ?>
								<ul class="uplinkportal-document-list">
									<?php foreach ( $client_documents as $document ) : ?>
										<li class="uplinkportal-document-list__item">
											<p class="uplinkportal-document-list__meta">
												<span><?php echo esc_html( (string) $document['type'] ); ?></span>
												<span><?php echo esc_html( (string) $document['status'] ); ?></span>
												<span><?php echo esc_html( (string) $document['updated_at'] ); ?></span>
											</p>
											<h4><?php echo esc_html( (string) $document['title'] ); ?></h4>
											<?php if ( '' !== (string) $document['summary'] ) : ?>
												<p><?php echo esc_html( (string) $document['summary'] ); ?></p>
											<?php endif; ?>
											<?php if ( '' !== (string) $document['url'] ) : ?>
												<p><a class="button button-secondary" href="<?php echo esc_url( (string) $document['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open Document', 'uplinkportal' ); ?></a></p>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p><?php echo esc_html__( 'No documents are available for your account yet.', 'uplinkportal' ); ?></p>
							<?php endif; ?>
						<?php elseif ( 'my-services' === $section['id'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="uplinkportal_create_purchase_request" />
								<input type="hidden" name="request_type" value="service_request" />
								<input type="hidden" name="request_context" value="client_portal" />
								<input type="hidden" name="redirect_to" value="<?php echo esc_url( $request_redirect ); ?>" />
								<?php wp_nonce_field( 'uplinkportal_create_purchase_request', 'uplinkportal_purchase_request_nonce' ); ?>
								<p>
									<label for="uplinkportal-client-request-item"><strong><?php echo esc_html__( 'Requested Service Area', 'uplinkportal' ); ?></strong></label><br />
									<select id="uplinkportal-client-request-item" name="requested_item_key">
										<?php foreach ( $request_items as $request_key => $request_label ) : ?>
											<option value="<?php echo esc_attr( sanitize_key( (string) $request_key ) ); ?>"><?php echo esc_html( sanitize_text_field( (string) $request_label ) ); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
								<p>
									<label for="uplinkportal-client-request-notes"><strong><?php echo esc_html__( 'Request Details', 'uplinkportal' ); ?></strong></label><br />
									<textarea id="uplinkportal-client-request-notes" name="notes" rows="4" class="large-text" placeholder="<?php echo esc_attr__( 'Describe what you need from your MSP team.', 'uplinkportal' ); ?>"></textarea>
								</p>
								<p>
									<button type="submit" class="button button-primary"><?php echo esc_html__( 'Submit Service Request', 'uplinkportal' ); ?></button>
								</p>
							</form>
							<div class="uplinkportal-module-state" data-uplinkportal-module-state data-module-key="subscriptions">
								<p data-uplinkportal-module-fallback><?php echo esc_html__( 'Live service data requires JavaScript. Submit requests above while data loading is unavailable.', 'uplinkportal' ); ?></p>
								<p data-uplinkportal-module-loading hidden><?php echo esc_html__( 'Loading service data...', 'uplinkportal' ); ?></p>
								<p data-uplinkportal-module-error hidden><?php echo esc_html__( 'Unable to load service data right now.', 'uplinkportal' ); ?></p>
								<p data-uplinkportal-module-empty hidden><?php echo esc_html__( 'No services were returned for your account.', 'uplinkportal' ); ?></p>
								<ul class="uplinkportal-module-list" data-uplinkportal-module-list hidden></ul>
							</div>
						<?php else : ?>
							<?php if ( 'help-desk' === $section['id'] || 'billing-center' === $section['id'] ) : ?>
								<?php $module_key = 'help-desk' === $section['id'] ? 'tickets' : 'billing'; ?>
								<div class="uplinkportal-module-state" data-uplinkportal-module-state data-module-key="<?php echo esc_attr( $module_key ); ?>">
									<p data-uplinkportal-module-fallback><?php echo esc_html__( 'Live module data requires JavaScript. Enable JavaScript or open the section from WordPress admin for manual review.', 'uplinkportal' ); ?></p>
									<p data-uplinkportal-module-loading hidden><?php echo esc_html__( 'Loading module data...', 'uplinkportal' ); ?></p>
									<p data-uplinkportal-module-error hidden><?php echo esc_html__( 'Unable to load module data right now.', 'uplinkportal' ); ?></p>
									<p data-uplinkportal-module-empty hidden><?php echo esc_html__( 'No records are currently available for this section.', 'uplinkportal' ); ?></p>
									<ul class="uplinkportal-module-list" data-uplinkportal-module-list hidden></ul>
								</div>
								<?php else : ?>
									<p>
										<?php echo esc_html__( 'This section will show live data after backend integration is connected.', 'uplinkportal' ); ?>
									</p>
								<?php endif; ?>
							<?php endif; ?>
					</section>
				<?php endforeach; ?>
			<?php else : ?>
				<section class="uplinkportal-section is-active">
					<h3><?php echo esc_html__( 'Getting Started', 'uplinkportal' ); ?></h3>
					<p><?php echo esc_html__( 'No workspace sections are enabled yet. Enable modules in UplinkPortal Configuration > Modules.', 'uplinkportal' ); ?></p>
				</section>
			<?php endif; ?>
		</main>
	</div>

	<p class="uplinkportal-footer-note" data-uplinkportal-preview-footer <?php echo '' === trim( $portal_footer_note ) ? 'hidden' : ''; ?>>
		<?php echo esc_html( $portal_footer_note ); ?>
	</p>
</div>
