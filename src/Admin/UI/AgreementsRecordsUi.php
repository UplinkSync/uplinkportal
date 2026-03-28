<?php
/**
 * Agreements & Records UI renderer.
 *
 * @package UplinkPortal\Admin\UI
 */

namespace UplinkPortal\Admin\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Agreements & Records admin page.
 *
 * This class is a rendering delegate extracted from SettingsPage to follow
 * the established UI/ pattern. Data is pre-computed by SettingsPage and passed
 * in via render(). The visual structure is preserved from the original
 * implementation; the hero gradient updates automatically from CSS tokens.
 */
class AgreementsRecordsUi {

	/**
	 * Renders the full Agreements & Records page body (inside the .wrap container).
	 *
	 * @param array<string,array<string,mixed>> $folders       Document folders keyed by folder ID.
	 * @param string                            $base_redirect Base admin URL for this page (for action targets).
	 * @param string                            $security_url  URL to Configuration > Security tab.
	 * @param string                            $modules_url   URL to Configuration > Modules tab.
	 * @param string                            $catalog_url   URL to Service Catalog page.
	 * @param string                            $doc_status    Status key from query arg.
	 * @param string                            $doc_error     Error key from query arg.
	 * @param int                               $doc_removed   Count of documents removed.
	 * @param int                               $doc_retained  Count of documents retained.
	 * @return void
	 */
	public function render(
		array $folders,
		string $base_redirect,
		string $security_url,
		string $modules_url,
		string $catalog_url,
		string $doc_status,
		string $doc_error,
		int $doc_removed,
		int $doc_retained
	): void {
		$keys        = array_keys( $folders );
		$has_folders = ! empty( $keys );

		$active_folder_id = $has_folders ? (string) $keys[0] : '';
		$active_folder    = $has_folders && isset( $folders[ $active_folder_id ] ) && is_array( $folders[ $active_folder_id ] )
			? $folders[ $active_folder_id ]
			: array();
		$active_documents = isset( $active_folder['documents'] ) && is_array( $active_folder['documents'] )
			? $active_folder['documents']
			: array();
		$active_document  = isset( $active_documents[0] ) && is_array( $active_documents[0] )
			? $active_documents[0]
			: array();
		$active_label     = isset( $active_folder['label'] ) ? sanitize_text_field( (string) $active_folder['label'] ) : '';
		$active_client_id = isset( $active_folder['client_id'] ) ? absint( $active_folder['client_id'] ) : 0;
		$active_doc_url   = isset( $active_document['url'] ) ? esc_url_raw( (string) $active_document['url'] ) : '';
		$has_active_url   = '' !== $active_doc_url;
		?>
		<section class="uplinkportal-admin-hero">
			<div>
				<h1><?php echo esc_html__( 'Agreements & Records', 'uplinkportal' ); ?></h1>
				<p><?php echo esc_html__( 'Browse records by client or organization, then review documents with an in-context preview panel.', 'uplinkportal' ); ?></p>
			</div>
			<span class="uplinkportal-admin-badge"><?php echo esc_html__( 'Core Functionality', 'uplinkportal' ); ?></span>
		</section>

		<?php $this->render_action_notices( $doc_status, $doc_error, $doc_removed, $doc_retained ); ?>

		<?php if ( ! $has_folders ) : ?>
			<div class="uplinkportal-admin-card">
				<p><?php echo esc_html__( 'No document folders found. Upload documents via the form below to create client document folders.', 'uplinkportal' ); ?></p>
			</div>
		<?php else : ?>
			<div class="uplinkportal-doc-browser uplinkportal-admin-card">
				<nav class="uplinkportal-doc-browser-sidebar" aria-label="<?php echo esc_attr__( 'Document folders', 'uplinkportal' ); ?>">
					<h3><?php echo esc_html__( 'Folders', 'uplinkportal' ); ?></h3>
					<ul>
						<?php foreach ( $folders as $folder_id => $folder ) : ?>
							<?php
							$folder_label = isset( $folder['label'] ) ? sanitize_text_field( (string) $folder['label'] ) : '';
							$doc_count    = isset( $folder['documents'] ) && is_array( $folder['documents'] ) ? count( $folder['documents'] ) : 0;
							$folder_url   = add_query_arg(
								array( 'folder_id' => rawurlencode( (string) $folder_id ) ),
								$base_redirect
							);
							?>
							<li>
								<a href="<?php echo esc_url( $folder_url ); ?>" class="<?php echo $folder_id === $active_folder_id ? 'is-active' : ''; ?>">
									<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
									<?php echo esc_html( $folder_label ); ?>
									<span class="uplinkportal-admin-badge" style="margin-left: auto;">
										<?php echo esc_html( (string) $doc_count ); ?>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<div class="uplinkportal-doc-browser-main">
					<h3><?php echo esc_html( $active_label ); ?></h3>

					<?php if ( empty( $active_documents ) ) : ?>
						<p><?php echo esc_html__( 'No documents in this folder.', 'uplinkportal' ); ?></p>
					<?php else : ?>
						<div class="uplinkportal-doc-workspace-grid">
							<ul class="uplinkportal-doc-list">
								<?php foreach ( $active_documents as $doc ) : ?>
									<?php
									$doc_label = isset( $doc['label'] ) ? sanitize_text_field( (string) $doc['label'] ) : '';
									$doc_url   = isset( $doc['url'] ) ? esc_url_raw( (string) $doc['url'] ) : '';
									$doc_type  = isset( $doc['type'] ) ? sanitize_key( (string) $doc['type'] ) : '';
									$doc_date  = isset( $doc['date'] ) ? sanitize_text_field( (string) $doc['date'] ) : '';
									?>
									<li class="uplinkportal-doc-list-item">
										<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
										<div>
											<strong><?php echo esc_html( $doc_label ); ?></strong>
											<span class="uplinkportal-status-pill"><?php echo esc_html( $doc_type ); ?></span>
										</div>
										<small><?php echo esc_html( $doc_date ); ?></small>
										<?php if ( '' !== $doc_url ) : ?>
											<a href="<?php echo esc_url( $doc_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
												<?php echo esc_html__( 'View', 'uplinkportal' ); ?>
											</a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>

							<?php if ( $has_active_url ) : ?>
								<div class="uplinkportal-doc-preview">
									<iframe
										src="<?php echo esc_url( $active_doc_url ); ?>"
										title="<?php echo esc_attr__( 'Document preview', 'uplinkportal' ); ?>"
										style="width:100%;height:600px;border:none;"
									></iframe>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php $this->render_upload_form( $base_redirect, $active_client_id, $folders ); ?>
		<?php
	}

	/**
	 * Renders status/error notices for document actions.
	 *
	 * @param string $doc_status   Status key.
	 * @param string $doc_error    Error key.
	 * @param int    $doc_removed  Count of removed documents.
	 * @param int    $doc_retained Count of retained documents.
	 * @return void
	 */
	private function render_action_notices( string $doc_status, string $doc_error, int $doc_removed, int $doc_retained ): void {
		if ( 'uploaded' === $doc_status ) {
			?>
			<div class="notice notice-success inline">
				<p><?php echo esc_html__( 'Document uploaded successfully.', 'uplinkportal' ); ?></p>
			</div>
			<?php
		}

		if ( 'purged' === $doc_status ) {
			?>
			<div class="notice notice-success inline">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: removed count, 2: retained count. */
							__( 'Purge complete: %1$d removed, %2$d retained.', 'uplinkportal' ),
							$doc_removed,
							$doc_retained
						)
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( '' !== $doc_error ) {
			?>
			<div class="notice notice-error inline">
				<p><?php echo esc_html( $doc_error ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Renders the document upload form.
	 *
	 * @param string                            $base_redirect  Base admin URL for this page.
	 * @param int                               $active_client_id Active client user ID.
	 * @param array<string,array<string,mixed>> $folders        Available folders.
	 * @return void
	 */
	private function render_upload_form( string $base_redirect, int $active_client_id, array $folders ): void {
		?>
		<div class="uplinkportal-admin-card">
			<h2><?php echo esc_html__( 'Upload Document', 'uplinkportal' ); ?></h2>
			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				enctype="multipart/form-data"
				class="uplinkportal-doc-upload-form"
			>
				<?php wp_nonce_field( 'uplinkportal_upload_client_document', 'uplinkportal_upload_client_document_nonce' ); ?>
				<input type="hidden" name="action" value="uplinkportal_upload_client_document" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $base_redirect ); ?>" />

				<label>
					<?php echo esc_html__( 'Document Label', 'uplinkportal' ); ?>
					<input type="text" class="regular-text" name="document_label" required />
				</label>

				<label>
					<?php echo esc_html__( 'Document Type', 'uplinkportal' ); ?>
					<select name="document_type">
						<option value="agreement"><?php echo esc_html__( 'Agreement', 'uplinkportal' ); ?></option>
						<option value="invoice"><?php echo esc_html__( 'Invoice', 'uplinkportal' ); ?></option>
						<option value="report"><?php echo esc_html__( 'Report', 'uplinkportal' ); ?></option>
						<option value="other"><?php echo esc_html__( 'Other', 'uplinkportal' ); ?></option>
					</select>
				</label>

				<label>
					<?php echo esc_html__( 'Client', 'uplinkportal' ); ?>
					<select name="client_id">
						<?php
						foreach ( $folders as $folder_id => $folder ) {
							$client_id = isset( $folder['client_id'] ) ? absint( $folder['client_id'] ) : 0;
							$label     = isset( $folder['label'] ) ? sanitize_text_field( (string) $folder['label'] ) : (string) $folder_id;
							echo '<option value="' . esc_attr( (string) $client_id ) . '" ' . selected( $client_id, $active_client_id, false ) . '>' . esc_html( $label ) . '</option>';
						}
						?>
					</select>
				</label>

				<label>
					<?php echo esc_html__( 'File', 'uplinkportal' ); ?>
					<input type="file" name="client_document" accept=".pdf,.docx,.xlsx,.png,.jpg" required />
				</label>

				<?php submit_button( __( 'Upload Document', 'uplinkportal' ) ); ?>
			</form>
		</div>
		<?php
	}
}
