<?php
/**
 * Encrypted client document storage service.
 *
 * @package UplinkPortal\Core\Support
 */

namespace UplinkPortal\Core\Support;

use WP_Error;
use WP_User;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Handles per-client encrypted document storage and retrieval.
 */
class ClientDocumentsStore {
	/**
	 * Option helper.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * DB table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Document audit table name.
	 *
	 * @var string
	 */
	private string $audit_table_name;

	/**
	 * Constructor.
	 *
	 * @param Options $options Option helper.
	 */
	public function __construct( Options $options ) {
		global $wpdb;
		$this->options = $options;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$this->table_name       = $wpdb->prefix . 'uplinkportal_client_documents';
			$this->audit_table_name = $wpdb->prefix . 'uplinkportal_client_document_audit';
		} else {
			// Fallback for test environment.
			$this->table_name       = 'wp_uplinkportal_client_documents';
			$this->audit_table_name = 'wp_uplinkportal_client_document_audit';
		}
	}

	/**
	 * Registers runtime hooks for directory provisioning.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'user_register', array( $this, 'handle_user_register' ), 20, 1 );
		add_action( 'set_user_role', array( $this, 'handle_set_user_role' ), 20, 3 );
		add_action( 'profile_update', array( $this, 'handle_profile_update' ), 20, 2 );
		add_action( 'init', array( $this, 'maybe_sync_client_directories' ), 20 );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_eraser' ) );
	}

	/**
	 * Ensures document index table exists.
	 *
	 * @return void
	 */
	public function ensure_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_user_id bigint(20) unsigned NOT NULL,
			uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
			original_name varchar(255) NOT NULL,
			stored_name varchar(255) NOT NULL,
			relative_path varchar(255) NOT NULL,
			mime_type varchar(100) NOT NULL DEFAULT 'application/pdf',
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			algorithm varchar(64) NOT NULL,
			nonce longtext NOT NULL,
			auth_tag longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_status (client_user_id,status),
			KEY status_updated (status,updated_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$audit_sql = "CREATE TABLE {$this->audit_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(50) NOT NULL,
			result varchar(20) NOT NULL DEFAULT 'success',
			message text NULL,
			ip_address varchar(45) NULL,
			user_agent varchar(255) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY doc_action (document_id,action),
			KEY client_action (client_user_id,action),
			KEY actor_created (actor_user_id,created_at)
		) {$charset_collate};";

		dbDelta( $audit_sql );
	}

	/**
	 * Returns indexed documents for a client with stream URLs.
	 *
	 * @param int $client_user_id Client user id.
	 * @return array<int,array<string,string>>
	 */
	public function get_documents_for_client( int $client_user_id ): array {
		global $wpdb;

		$client_user_id = absint( $client_user_id );
		if ( $client_user_id <= 0 ) {
			return array();
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, original_name, mime_type, file_size, status, updated_at
				FROM {$this->table_name}
				WHERE client_user_id = %d AND status = %s
				ORDER BY updated_at DESC, id DESC",
				$client_user_id,
				'active'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$documents = array();
		foreach ( $rows as $row ) {
			$document_id   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$original_name = isset( $row['original_name'] ) ? sanitize_text_field( (string) $row['original_name'] ) : '';
			if ( $document_id <= 0 || '' === $original_name ) {
				continue;
			}

			$type_label = __( 'PDF', 'uplinkportal' );
			$file_size  = isset( $row['file_size'] ) ? absint( $row['file_size'] ) : 0;
			if ( $file_size > 0 ) {
				$type_label = sprintf(
					/* translators: 1: file type, 2: formatted file size. */
					__( '%1$s (%2$s)', 'uplinkportal' ),
					$type_label,
					size_format( $file_size, 1 )
				);
			}

			$updated_label = '';
			if ( ! empty( $row['updated_at'] ) ) {
				$updated_label = mysql2date( 'Y-m-d', (string) $row['updated_at'], false );
			}

			$documents[] = array(
				'title'      => $original_name,
				'type'       => $type_label,
				'status'     => __( 'Encrypted', 'uplinkportal' ),
				'updated_at' => $updated_label,
				'summary'    => __( 'Encrypted file available to your MSP provider and your client account.', 'uplinkportal' ),
				'url'        => $this->build_stream_url( $document_id ),
			);
		}

		return $documents;
	}

	/**
	 * Uploads and encrypts one client document.
	 *
	 * @param int                 $client_user_id Client user id.
	 * @param array<string,mixed> $file Uploaded file array.
	 * @param int                 $uploaded_by User id that uploaded file.
	 * @return int|WP_Error
	 */
	public function upload_document_for_client( int $client_user_id, array $file, int $uploaded_by = 0 ) {
		global $wpdb;

		$client_user_id = absint( $client_user_id );
		$uploaded_by    = absint( $uploaded_by );

		if ( $client_user_id <= 0 || ! $this->is_client_workspace_user( $client_user_id ) ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'invalid_client' );
			return new WP_Error( 'invalid_client', __( 'Invalid client selected.', 'uplinkportal' ) );
		}

		$this->ensure_client_directory_for_user( $client_user_id );

		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'missing_file' );
			return new WP_Error( 'missing_file', __( 'No file selected.', 'uplinkportal' ) );
		}

		$error_code = isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error_code ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'upload_error' );
			return new WP_Error( 'upload_error', __( 'Upload failed. Please try again.', 'uplinkportal' ) );
		}

		$tmp_name = (string) $file['tmp_name'];
		$name     = sanitize_file_name( (string) wp_basename( (string) $file['name'] ) );
		if ( '' === $name || ! is_uploaded_file( $tmp_name ) ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'invalid_upload' );
			return new WP_Error( 'invalid_upload', __( 'Uploaded file could not be validated.', 'uplinkportal' ) );
		}

		$validation = $this->validate_uploaded_pdf( $file, $name, $tmp_name );
		if ( is_wp_error( $validation ) ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', (string) $validation->get_error_code() );
			return $validation;
		}

		$bytes = file_get_contents( $tmp_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads validated uploaded temp file.
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'read_error' );
			return new WP_Error( 'read_error', __( 'Unable to read uploaded file.', 'uplinkportal' ) );
		}

		$key = $this->get_client_encryption_key( $client_user_id );
		if ( '' === $key ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'uplinkportal_key_retrieval_failed' );
			return new WP_Error( 'uplinkportal_key_retrieval_failed', __( 'No document encryption key is available from backend connection settings.', 'uplinkportal' ) );
		}

		$encrypted = $this->encrypt_payload( $bytes, $key, $client_user_id );
		if ( is_wp_error( $encrypted ) ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', (string) $encrypted->get_error_code() );
			return $encrypted;
		}

		$relative_folder = $this->get_client_relative_folder( $client_user_id );
		$stored_name     = 'doc_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 12, false, false ) . '.uplink';
		$absolute_path   = trailingslashit( $this->get_base_storage_dir() ) . $relative_folder . '/' . $stored_name;

		$write_result = file_put_contents( $absolute_path, (string) $encrypted['ciphertext'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes encrypted payload to plugin-controlled storage path.
		if ( false === $write_result ) {
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'write_error' );
			return new WP_Error( 'write_error', __( 'Unable to store encrypted document.', 'uplinkportal' ) );
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$this->table_name,
			array(
				'client_user_id' => $client_user_id,
				'uploaded_by'    => $uploaded_by,
				'original_name'  => $name,
				'stored_name'    => $stored_name,
				'relative_path'  => $relative_folder . '/' . $stored_name,
				'mime_type'      => 'application/pdf',
				'file_size'      => strlen( $bytes ),
				'algorithm'      => (string) $encrypted['algorithm'],
				'nonce'          => (string) $encrypted['nonce'],
				'auth_tag'       => (string) $encrypted['auth_tag'],
				'status'         => 'active',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( empty( $wpdb->insert_id ) ) {
			wp_delete_file( $absolute_path );
			$this->log_document_event( 'upload', $client_user_id, 0, 'error', 'db_insert_error' );
			return new WP_Error( 'db_insert_error', __( 'Unable to index uploaded document.', 'uplinkportal' ) );
		}

		$this->log_document_event( 'upload', $client_user_id, absint( $wpdb->insert_id ), 'success', 'uploaded_encrypted' );

		return absint( $wpdb->insert_id );
	}

	/**
	 * Streams decrypted document output to current request.
	 *
	 * @param int $document_id Document id.
	 * @return true|WP_Error
	 */
	public function stream_document( int $document_id ) {
		global $wpdb;

		$document_id = absint( $document_id );
		if ( $document_id <= 0 ) {
			$this->log_document_event( 'view', 0, 0, 'error', 'invalid_document' );
			return new WP_Error( 'invalid_document', __( 'Invalid document requested.', 'uplinkportal' ) );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled and not user input.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, client_user_id, original_name, relative_path, mime_type, algorithm, nonce, auth_tag, status
				FROM {$this->table_name}
				WHERE id = %d",
				$document_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $row ) || 'active' !== (string) $row['status'] ) {
			$this->log_document_event( 'view', 0, $document_id, 'error', 'not_found' );
			return new WP_Error( 'not_found', __( 'Document was not found.', 'uplinkportal' ) );
		}

		$client_user_id = isset( $row['client_user_id'] ) ? absint( $row['client_user_id'] ) : 0;
		if ( ! $this->can_user_access_document( get_current_user_id(), $client_user_id ) ) {
			$this->log_document_event( 'view', $client_user_id, $document_id, 'denied', 'forbidden' );
			return new WP_Error( 'forbidden', __( 'You are not allowed to access this document.', 'uplinkportal' ) );
		}

		$relative_path = isset( $row['relative_path'] ) ? (string) $row['relative_path'] : '';
		$absolute_path = $this->resolve_document_absolute_path( $relative_path );
		if ( '' === $relative_path || ! file_exists( $absolute_path ) ) {
			$this->log_document_event( 'view', $client_user_id, $document_id, 'error', 'missing_file' );
			return new WP_Error( 'missing_file', __( 'Encrypted file is missing from storage.', 'uplinkportal' ) );
		}

		$ciphertext = file_get_contents( $absolute_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads encrypted payload from plugin-controlled storage path.
		if ( ! is_string( $ciphertext ) || '' === $ciphertext ) {
			$this->log_document_event( 'view', $client_user_id, $document_id, 'error', 'read_error' );
			return new WP_Error( 'read_error', __( 'Unable to read encrypted file.', 'uplinkportal' ) );
		}

		$key = $this->get_client_encryption_key( $client_user_id );
		if ( '' === $key ) {
			$this->log_document_event( 'view', $client_user_id, $document_id, 'error', 'missing_key' );
			return new WP_Error( 'missing_key', __( 'No document encryption key is available from backend connection settings.', 'uplinkportal' ) );
		}

		$plaintext = $this->decrypt_payload(
			$ciphertext,
			(string) $row['algorithm'],
			(string) $row['nonce'],
			(string) $row['auth_tag'],
			$key
		);

		if ( false === $plaintext ) {
			$this->log_document_event( 'view', $client_user_id, $document_id, 'error', 'decrypt_error' );
			return new WP_Error( 'decrypt_error', __( 'Unable to decrypt this document.', 'uplinkportal' ) );
		}

		$this->log_document_event( 'view', $client_user_id, $document_id, 'success', 'streamed' );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( sanitize_file_name( (string) $row['original_name'] ) ) . '"' );
		header( 'Content-Length: ' . (string) strlen( $plaintext ) );
		echo $plaintext; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF stream.
		exit;
	}

	/**
	 * Ensures base + per-client document directories are present for all clients.
	 *
	 * @return void
	 */
	public function maybe_sync_client_directories(): void {
		if ( get_transient( 'uplinkportal_docs_dir_sync_lock' ) ) {
			return;
		}

		set_transient( 'uplinkportal_docs_dir_sync_lock', 1, 3600 );
		foreach ( $this->get_client_workspace_users() as $user ) {
			$this->ensure_client_directory_for_user( (int) $user->ID );
		}
	}

	/**
	 * Creates client directory when user is created.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function handle_user_register( int $user_id ): void {
		$this->ensure_client_directory_for_user( $user_id );
	}

	/**
	 * Creates client directory when role is changed.
	 *
	 * @param int    $user_id User id.
	 * @param string $role New role.
	 * @param array  $old_roles Previous roles.
	 * @return void
	 */
	public function handle_set_user_role( int $user_id, string $role, array $old_roles ): void {
		unset( $old_roles );
		if ( '' === sanitize_key( $role ) ) {
			return;
		}
		$this->ensure_client_directory_for_user( $user_id );
	}

	/**
	 * Creates client directory when profile updates.
	 *
	 * @param int     $user_id User id.
	 * @param WP_User $old_user_data Previous user object.
	 * @return void
	 */
	public function handle_profile_update( int $user_id, WP_User $old_user_data ): void {
		unset( $old_user_data );
		$this->ensure_client_directory_for_user( $user_id );
	}

	/**
	 * Returns stream URL with nonce.
	 *
	 * @param int $document_id Document id.
	 * @return string
	 */
	private function build_stream_url( int $document_id ): string {
		$url = add_query_arg(
			array(
				'action'      => 'uplinkportal_stream_client_document',
				'document_id' => absint( $document_id ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'uplinkportal_stream_client_document_' . absint( $document_id ) );
	}

	/**
	 * Ensures storage folder exists for one client.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	protected function ensure_client_directory_for_user( int $user_id ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $this->is_client_workspace_user( $user_id ) ) {
			return;
		}

		$base_dir = $this->get_base_storage_dir();
		wp_mkdir_p( $base_dir );
		$sub_dir = trailingslashit( $base_dir ) . $this->get_client_relative_folder( $user_id );
		wp_mkdir_p( $sub_dir );
		$this->write_directory_shields( $base_dir );
		$this->write_directory_shields( $sub_dir );
	}

	/**
	 * Returns storage base directory in uploads.
	 *
	 * @return string
	 */
	private function get_base_storage_dir(): string {
		$filtered = apply_filters( 'uplinkportal_client_documents_storage_dir', '' );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return untrailingslashit( wp_normalize_path( $filtered ) );
		}

		$preferred_parent = untrailingslashit( wp_normalize_path( dirname( ABSPATH ) ) );
		if ( $this->is_directory_writable( $preferred_parent ) ) {
			return $preferred_parent . '/uplinkportal-private';
		}

		return untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . '/uplinkportal-private';
	}

	/**
	 * Returns relative directory path for a client.
	 *
	 * @param int $client_user_id Client user id.
	 * @return string
	 */
	private function get_client_relative_folder( int $client_user_id ): string {
		return 'client-' . absint( $client_user_id );
	}

	/**
	 * Writes simple directory access shields.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function write_directory_shields( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$index_file = trailingslashit( $directory ) . 'index.html';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes minimal directory shield file in plugin-controlled directory.
		}

		$htaccess_file = trailingslashit( $directory ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes minimal directory shield file in plugin-controlled directory.
		}
	}

	/**
	 * Returns absolute document path in current storage.
	 *
	 * @param string $relative_path Relative path.
	 * @return string
	 */
	private function resolve_document_absolute_path( string $relative_path ): string {
		$relative_path = ltrim( $relative_path, '/\\' );
		if ( '' === $relative_path ) {
			return '';
		}

		return trailingslashit( $this->get_base_storage_dir() ) . $relative_path;
	}

	/**
	 * Returns whether user can access a document for a client.
	 *
	 * @param int $user_id Current user id.
	 * @param int $client_user_id Document owner client user id.
	 * @return bool
	 */
	private function can_user_access_document( int $user_id, int $client_user_id ): bool {
		if ( $user_id <= 0 || $client_user_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'msp_manage' ) ) {
			return true;
		}

		return $user_id === $client_user_id;
	}

	/**
	 * Returns key material for one client's documents.
	 *
	 * @param int $client_user_id Client user id.
	 * @return string
	 */
	protected function get_client_encryption_key( int $client_user_id ): string {
		$client_user_id = absint( $client_user_id );
		if ( $client_user_id <= 0 ) {
			return '';
		}

		$filtered = apply_filters( 'uplinkportal_client_documents_encryption_key', '', $client_user_id, $this->options );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return $this->normalize_key_material( $filtered );
		}

		$transient_key = 'uplinkportal_doc_key_' . $client_user_id;
		$cached_key    = get_transient( $transient_key );
		if ( is_string( $cached_key ) && '' !== $cached_key ) {
			return $cached_key;
		}

		$backend_key = $this->fetch_key_from_backend( $client_user_id );
		if ( '' !== $backend_key ) {
			set_transient( $transient_key, $backend_key, 10 * MINUTE_IN_SECONDS );
			return $backend_key;
		}

		return '';
	}

	/**
	 * Requests encryption key material from backend service.
	 *
	 * @param int $client_user_id Client user id.
	 * @return string
	 */
	private function fetch_key_from_backend( int $client_user_id ): string {
		$base_url = $this->options->get_backend_base_url();
		if ( '' === $base_url ) {
			return '';
		}

		$transport_check = TransportSecurityPolicy::validate_url( $base_url, 'documents_backend' );
		if ( is_wp_error( $transport_check ) ) {
			return '';
		}

		$service_token = $this->options->get_backend_service_token();
		if ( '' === $service_token ) {
			return '';
		}

		$endpoint = apply_filters( 'uplinkportal_client_documents_key_endpoint', '/uplinkportal/v1/client-documents/key' );
		$endpoint = is_string( $endpoint ) ? $endpoint : '';
		if ( '' === $endpoint ) {
			return '';
		}

		$url = untrailingslashit( $base_url ) . '/' . ltrim( $endpoint, '/' );
		$raw = wp_remote_post(
			$url,
			TransportSecurityPolicy::secure_remote_args(
				array(
					'timeout' => 8,
					'headers' => array(
						'Authorization' => 'Bearer ' . $service_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'client_user_id' => absint( $client_user_id ),
							'instance_id'    => $this->options->get_instance_id(),
							'site_url'       => home_url( '/' ),
						)
					),
				)
			)
		);

		if ( is_wp_error( $raw ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $raw );
		if ( $code < 200 || $code >= 300 ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $raw );
		if ( ! is_string( $body ) || '' === $body ) {
			return '';
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) || empty( $decoded['key'] ) ) {
			return '';
		}

		return $this->normalize_key_material( (string) $decoded['key'] );
	}

	/**
	 * Validates uploaded file as supported PDF.
	 *
	 * @param array<string,mixed> $file Uploaded file payload.
	 * @param string              $name Sanitized filename.
	 * @param string              $tmp_name Uploaded temp path.
	 * @return true|WP_Error
	 */
	protected function validate_uploaded_pdf( array $file, string $name, string $tmp_name ) {
		$size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		$max  = (int) apply_filters( 'uplinkportal_client_documents_max_upload_bytes', 10 * MB_IN_BYTES );
		$max  = max( MB_IN_BYTES, $max );
		if ( $size <= 0 || $size > $max ) {
			return new WP_Error( 'invalid_size', __( 'File size is not allowed.', 'uplinkportal' ) );
		}

		$check = wp_check_filetype_and_ext( $tmp_name, $name, array( 'pdf' => 'application/pdf' ) );
		$ext   = isset( $check['ext'] ) ? sanitize_key( (string) $check['ext'] ) : '';
		$type  = isset( $check['type'] ) ? sanitize_text_field( (string) $check['type'] ) : '';
		if ( 'pdf' !== $ext || '' === $type || ! str_contains( strtolower( $type ), 'pdf' ) ) {
			return new WP_Error( 'invalid_type', __( 'Only PDF files are supported.', 'uplinkportal' ) );
		}

		$handle = fopen( $tmp_name, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads uploaded temp file for signature validation.
		if ( false === $handle ) {
			return new WP_Error( 'invalid_upload', __( 'Uploaded file could not be validated.', 'uplinkportal' ) );
		}
		$signature = fread( $handle, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reads uploaded temp file for signature validation.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes uploaded temp file handle.
		if ( ! is_string( $signature ) || '%PDF-' !== $signature ) {
			return new WP_Error( 'invalid_content', __( 'Uploaded file is not a valid PDF document.', 'uplinkportal' ) );
		}

		return true;
	}

	/**
	 * Returns whether directory path is writable or can be created.
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	private function is_directory_writable( string $path ): bool {
		if ( is_dir( $path ) ) {
			return is_writable( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Directory writability check for private path fallback.
		}

		return is_writable( dirname( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Directory writability check for private path fallback.
	}

	/**
	 * Returns storage usage metrics for admin display.
	 *
	 * @return array<string,mixed>
	 */
	public function get_storage_usage_metrics(): array {
		global $wpdb;

		$doc_count = 0;
		$total     = 0;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are plugin-controlled.
		$doc_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
				'active'
			)
		);
		$total     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(file_size),0) FROM {$this->table_name} WHERE status = %s",
				'active'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$quota = (int) apply_filters( 'uplinkportal_client_documents_soft_quota_bytes', 2 * GB_IN_BYTES );
		$quota = max( MB_IN_BYTES, $quota );
		$base  = $this->get_base_storage_dir();

		$disk_probe = $base;
		if ( ! is_dir( $disk_probe ) ) {
			$disk_probe = dirname( $disk_probe );
		}
		$free = false;
		if ( is_dir( $disk_probe ) ) {
			$free = disk_free_space( $disk_probe );
		}

		$usage_percent = ( $quota > 0 ) ? min( 100, round( ( $total / $quota ) * 100, 2 ) ) : 0;
		$uploads       = wp_get_upload_dir();
		$uploads_base  = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
		$is_private    = '' === $uploads_base || 0 !== strpos( wp_normalize_path( $base ), $uploads_base );

		// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		return array(
			'document_count'   => max( 0, $doc_count ),
			'usage_bytes'      => max( 0, $total ),
			'quota_bytes'      => $quota,
			'usage_percent'    => $usage_percent,
			'free_bytes'       => ( false !== $free ) ? max( 0, (int) $free ) : 0,
			'base_directory'   => $base,
			'private_storage'  => $is_private,
		);
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	}

	/**
	 * Purges document audit entries older than N days.
	 *
	 * @param int $days Retention days.
	 * @return int
	 */
	public function purge_document_audit_entries_older_than( int $days ): int {
		global $wpdb;

		$days   = max( 1, absint( $days ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->audit_table_name} WHERE created_at < %s",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false === $result ? 0 : max( 0, absint( $result ) );
	}

	/**
	 * Deletes all documents owned by a client user.
	 *
	 * @param int $client_user_id Client user id.
	 * @param int $actor_user_id Actor performing deletion.
	 * @return array<string,int>
	 */
	public function delete_documents_for_client( int $client_user_id, int $actor_user_id = 0 ): array {
		global $wpdb;

		$client_user_id = absint( $client_user_id );
		$actor_user_id  = absint( $actor_user_id );
		if ( $client_user_id <= 0 ) {
			return array(
				'removed'  => 0,
				'retained' => 0,
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are plugin-controlled.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, relative_path
				FROM {$this->table_name}
				WHERE client_user_id = %d AND status = %s",
				$client_user_id,
				'active'
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array(
				'removed'  => 0,
				'retained' => 0,
			);
		}

		$removed  = 0;
		$retained = 0;
		foreach ( $rows as $row ) {
			$document_id   = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$relative_path = isset( $row['relative_path'] ) ? (string) $row['relative_path'] : '';
			$file_deleted  = true;
			$current_path  = trailingslashit( $this->get_base_storage_dir() ) . ltrim( $relative_path, '/\\' );

			if ( '' !== $current_path && file_exists( $current_path ) ) {
				$deleted = wp_delete_file( $current_path );
				if ( false === $deleted ) {
					$file_deleted = false;
				}
			}

			$deleted_row = $wpdb->delete(
				$this->table_name,
				array(
					'id'             => $document_id,
					'client_user_id' => $client_user_id,
				),
				array( '%d', '%d' )
			);

			if ( false !== $deleted_row && $file_deleted ) {
				++$removed;
				$this->log_document_event( 'delete', $client_user_id, $document_id, 'success', 'deleted', $actor_user_id );
				continue;
			}

			++$retained;
			$this->log_document_event( 'delete', $client_user_id, $document_id, 'error', 'delete_failed', $actor_user_id );
		}

		$this->log_document_event(
			'erase_batch',
			$client_user_id,
			0,
			0 === $retained ? 'success' : 'partial',
			'removed:' . $removed . '|retained:' . $retained . '|actor:' . $actor_user_id,
			$actor_user_id
		);

		return array(
			'removed'  => $removed,
			'retained' => $retained,
		);
	}

	/**
	 * Registers privacy exporter callback.
	 *
	 * @param array<string,array<string,mixed>> $exporters Existing exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_personal_data_exporter( array $exporters ): array {
		$exporters['uplinkportal-client-documents'] = array(
			'exporter_friendly_name' => __( 'UplinkPortal Client Documents', 'uplinkportal' ),
			'callback'               => array( $this, 'privacy_export_client_documents' ),
		);

		return $exporters;
	}

	/**
	 * Exports personal data entries for client documents.
	 *
	 * @param string $email_address Request email.
	 * @param int    $page Page number.
	 * @return array<string,mixed>
	 */
	public function privacy_export_client_documents( string $email_address, int $page = 1 ): array {
		unset( $page );

		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User || ! $this->is_client_workspace_user( (int) $user->ID ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, original_name, mime_type, file_size, status, created_at, updated_at
				FROM {$this->table_name}
				WHERE client_user_id = %d
				ORDER BY id ASC",
				(int) $user->ID
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$data = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$document_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( $document_id <= 0 ) {
					continue;
				}

				$data[] = array(
					'group_id'    => 'uplinkportal_documents',
					'group_label' => __( 'UplinkPortal Documents', 'uplinkportal' ),
					'item_id'     => 'uplinkportal_document_' . $document_id,
					'data'        => array(
						array(
							'name'  => __( 'Document ID', 'uplinkportal' ),
							'value' => (string) $document_id,
						),
						array(
							'name'  => __( 'Filename', 'uplinkportal' ),
							'value' => isset( $row['original_name'] ) ? sanitize_text_field( (string) $row['original_name'] ) : '',
						),
						array(
							'name'  => __( 'Type', 'uplinkportal' ),
							'value' => isset( $row['mime_type'] ) ? sanitize_text_field( (string) $row['mime_type'] ) : '',
						),
						array(
							'name'  => __( 'Size (bytes)', 'uplinkportal' ),
							'value' => isset( $row['file_size'] ) ? (string) absint( $row['file_size'] ) : '0',
						),
						array(
							'name'  => __( 'Status', 'uplinkportal' ),
							'value' => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '',
						),
						array(
							'name'  => __( 'Created (UTC)', 'uplinkportal' ),
							'value' => isset( $row['created_at'] ) ? sanitize_text_field( (string) $row['created_at'] ) : '',
						),
						array(
							'name'  => __( 'Updated (UTC)', 'uplinkportal' ),
							'value' => isset( $row['updated_at'] ) ? sanitize_text_field( (string) $row['updated_at'] ) : '',
						),
					),
				);
			}
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Registers privacy eraser callback.
	 *
	 * @param array<string,array<string,mixed>> $erasers Existing erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_personal_data_eraser( array $erasers ): array {
		$erasers['uplinkportal-client-documents'] = array(
			'eraser_friendly_name' => __( 'UplinkPortal Client Documents', 'uplinkportal' ),
			'callback'             => array( $this, 'privacy_erase_client_documents' ),
		);

		return $erasers;
	}

	/**
	 * Erases personal data entries for client documents.
	 *
	 * @param string $email_address Request email.
	 * @param int    $page Page number.
	 * @return array<string,mixed>
	 */
	public function privacy_erase_client_documents( string $email_address, int $page = 1 ): array {
		unset( $page );

		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User || ! $this->is_client_workspace_user( (int) $user->ID ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$result = $this->delete_documents_for_client( (int) $user->ID, get_current_user_id() );

		return array(
			'items_removed'  => ! empty( $result['removed'] ),
			'items_retained' => ! empty( $result['retained'] ),
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Writes one event into the document audit trail.
	 *
	 * @param string $action Event action.
	 * @param int    $client_user_id Client user id.
	 * @param int    $document_id Document id.
	 * @param string $result Result label.
	 * @param string $message Message text.
	 * @param int    $actor_user_id Optional actor user id override.
	 * @return void
	 */
	protected function log_document_event( string $action, int $client_user_id, int $document_id, string $result, string $message, int $actor_user_id = 0 ): void {
		global $wpdb;

		$action   = substr( sanitize_key( $action ), 0, 50 );
		$result   = substr( sanitize_key( $result ), 0, 20 );
		$message  = SensitiveDataPseudonymizer::pseudonymize_message( $message );
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ip       = SensitiveDataPseudonymizer::pseudonymize_ip( $ip );
		$agent    = SensitiveDataPseudonymizer::pseudonymize_user_agent( $agent );
		$agent    = substr( $agent, 0, 255 );
		$actor_id = $actor_user_id > 0 ? absint( $actor_user_id ) : get_current_user_id();

		$wpdb->insert(
			$this->audit_table_name,
			array(
				'document_id'    => absint( $document_id ),
				'client_user_id' => absint( $client_user_id ),
				'actor_user_id'  => absint( $actor_id ),
				'action'         => '' !== $action ? $action : 'unknown',
				'result'         => '' !== $result ? $result : 'error',
				'message'        => $message,
				'ip_address'     => $ip,
				'user_agent'     => $agent,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		/**
		 * Fires when a document audit event is recorded.
		 *
		 * @param array<string,mixed> $payload Event payload.
		 */
		do_action(
			'uplinkportal_security_audit_event',
			array(
				'source'         => 'client_documents',
				'time'           => gmdate( 'c' ),
				'event'          => $action,
				'result'         => $result,
				'message'        => $message,
				'actor_user_id'  => absint( $actor_id ),
				'client_user_id' => absint( $client_user_id ),
				'document_id'    => absint( $document_id ),
				'ip'             => $ip,
			)
		);
	}

	/**
	 * Normalizes key material into 32-byte binary key.
	 *
	 * @param string $raw Raw key material.
	 * @return string
	 */
	private function normalize_key_material( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		$decoded = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Transport decoding for key material.
		if ( is_string( $decoded ) && strlen( $decoded ) >= 32 ) {
			return substr( $decoded, 0, 32 );
		}

		if ( strlen( $raw ) >= 32 ) {
			return substr( hash( 'sha256', $raw, true ), 0, 32 );
		}

		return hash( 'sha256', $raw, true );
	}

	/**
	 * Encrypts binary payload.
	 *
	 * @param string $plaintext Binary content.
	 * @param string $key Key bytes.
	 * @param int    $client_user_id Client id for AAD/scope.
	 * @return array<string,string>|WP_Error
	 */
	private function encrypt_payload( string $plaintext, string $key, int $client_user_id ) {
		if ( function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return array(
				'algorithm'  => 'sodium_secretbox_v1',
				'nonce'      => base64_encode( $nonce ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary nonce transport.
				'auth_tag'   => '',
				'ciphertext' => $ciphertext,
			);
		}

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) ) {
			$iv         = random_bytes( 12 );
			$auth_tag   = '';
			$cipher     = 'aes-256-gcm';
			$ciphertext = openssl_encrypt(
				$plaintext,
				$cipher,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$auth_tag,
				'uplinkportal-client-' . $client_user_id
			);
			if ( ! is_string( $ciphertext ) ) {
				return new WP_Error( 'encrypt_failed', __( 'Unable to encrypt file payload.', 'uplinkportal' ) );
			}

			return array(
				'algorithm'  => 'aes_256_gcm_v1',
				'nonce'      => base64_encode( $iv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary IV transport.
				'auth_tag'   => base64_encode( (string) $auth_tag ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary tag transport.
				'ciphertext' => $ciphertext,
			);
		}

		return new WP_Error( 'no_encryption', __( 'No supported encryption library is available on this server.', 'uplinkportal' ) );
	}

	/**
	 * Decrypts one encrypted payload.
	 *
	 * @param string $ciphertext Ciphertext.
	 * @param string $algorithm Algorithm id.
	 * @param string $nonce_base64 Nonce/IV.
	 * @param string $auth_tag_base64 Auth tag.
	 * @param string $key Key bytes.
	 * @return string|false
	 */
	private function decrypt_payload( string $ciphertext, string $algorithm, string $nonce_base64, string $auth_tag_base64, string $key ) {
		$algorithm = sanitize_key( $algorithm );
		$nonce     = base64_decode( $nonce_base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary nonce decode.
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		if ( 'sodium_secretbox_v1' === $algorithm && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		}

		if ( 'aes_256_gcm_v1' === $algorithm && function_exists( 'openssl_decrypt' ) ) {
			$auth_tag = base64_decode( $auth_tag_base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary auth tag decode.
			if ( ! is_string( $auth_tag ) ) {
				return false;
			}

			return openssl_decrypt(
				$ciphertext,
				'aes-256-gcm',
				$key,
				OPENSSL_RAW_DATA,
				$nonce,
				$auth_tag
			);
		}

		return false;
	}

	/**
	 * Returns client users in plugin client roles.
	 *
	 * @return array<int,WP_User>
	 */
	private function get_client_workspace_users(): array {
		$query_args = array(
			'role__in' => array(
				'uplinkportal_client_owner',
				'uplinkportal_client_manager',
				'uplinkportal_client_project_manager',
				'uplinkportal_client_finance',
				'uplinkportal_client_tech',
				'uplinkportal_client_user',
			),
			'fields'   => 'all',
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		);
		$query_args = TenantBoundaryScope::apply_user_query_scope( $query_args, $this->options );
		$users      = get_users( $query_args );

		if ( ! is_array( $users ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$users,
				static function ( $user ): bool {
					return $user instanceof WP_User;
				}
			)
		);
	}

	/**
	 * Returns true when user belongs to one client workspace role.
	 *
	 * @param int $user_id User id.
	 * @return bool
	 */
	protected function is_client_workspace_user( int $user_id ): bool {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		if ( ! TenantBoundaryScope::is_user_in_scope( $user_id, $this->options ) ) {
			return false;
		}

		foreach ( array( 'uplinkportal_client_owner', 'uplinkportal_client_manager', 'uplinkportal_client_project_manager', 'uplinkportal_client_finance', 'uplinkportal_client_tech', 'uplinkportal_client_user' ) as $role_slug ) {
			if ( in_array( $role_slug, $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
