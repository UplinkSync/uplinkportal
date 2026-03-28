<?php
/**
 * ClientDocumentsStore fail-closed test.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\ClientDocumentsStore;
use UplinkPortal\Core\Support\Options;

/**
 * Tests that document upload returns WP_Error when the encryption key is unavailable.
 */
class ClientDocumentsStoreTest extends TestCase {
	/**
	 * Path to the temporary test file.
	 *
	 * @var string
	 */
	private string $tmp_file = '';

	/**
	 * Creates a minimal temp file with PDF magic bytes for upload simulation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_transients'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();

		$this->tmp_file = tempnam( sys_get_temp_dir(), 'uplinkportal_test_' );
		// Write minimal PDF magic bytes so mime validation passes.
		file_put_contents( $this->tmp_file, '%PDF-1.4 test content' );
	}

	/**
	 * Cleans up temp file after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->tmp_file && file_exists( $this->tmp_file ) ) {
			unlink( $this->tmp_file );
		}
		parent::tearDown();
	}

	/**
	 * Upload_document_for_client() returns WP_Error with code uplinkportal_key_retrieval_failed
	 * when the key endpoint is unreachable (get_client_encryption_key returns empty string).
	 *
	 * @return void
	 */
	public function test_upload_fails_closed_when_key_unavailable(): void {
		$store = $this->build_testable_store();

		$file = array(
			'name'     => 'test-doc.pdf',
			'tmp_name' => $this->tmp_file,
			'size'     => filesize( $this->tmp_file ),
			'error'    => UPLOAD_ERR_OK,
		);

		$result = $store->upload_document_for_client( 1, $file, 0 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'uplinkportal_key_retrieval_failed', $result->get_error_code() );
	}

	/**
	 * Upload_document_for_client() writes no file to disk when key is unavailable.
	 *
	 * @return void
	 */
	public function test_no_file_written_to_disk_when_key_unavailable(): void {
		$store = $this->build_testable_store();

		$file = array(
			'name'     => 'test-doc.pdf',
			'tmp_name' => $this->tmp_file,
			'size'     => filesize( $this->tmp_file ),
			'error'    => UPLOAD_ERR_OK,
		);

		$files_before = iterator_to_array(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( sys_get_temp_dir(), \FilesystemIterator::SKIP_DOTS )
			)
		);

		$store->upload_document_for_client( 1, $file, 0 );

		$files_after = iterator_to_array(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( sys_get_temp_dir(), \FilesystemIterator::SKIP_DOTS )
			)
		);

		// No new .uplink files created.
		$new_uplink_files = array_filter(
			array_keys( $files_after ),
			static function ( string $path ) use ( $files_before ): bool {
				return str_ends_with( $path, '.uplink' ) && ! isset( $files_before[ $path ] );
			}
		);

		$this->assertEmpty( $new_uplink_files, 'No encrypted .uplink file should be written when key retrieval fails.' );
	}

	/**
	 * Builds a testable ClientDocumentsStore subclass that:
	 * - Bypasses is_client_workspace_user() to return true for user ID 1
	 * - Bypasses ensure_client_directory_for_user() as no-op
	 * - Bypasses validate_uploaded_pdf() to return true
	 * - Overrides get_client_encryption_key() to return '' (simulating key retrieval failure)
	 * - Bypasses is_uploaded_file() requirement
	 *
	 * @return ClientDocumentsStore
	 */
	private function build_testable_store(): ClientDocumentsStore {
		$options = $this->createMock( Options::class );

		return new class( $options ) extends ClientDocumentsStore {
			/**
			 * Always treats user 1 as a workspace user.
			 *
			 * @param int $user_id User id.
			 * @return bool
			 */
			protected function is_client_workspace_user( int $user_id ): bool {
				return 1 === $user_id;
			}

			/**
			 * No-op directory provisioning.
			 *
			 * @param int $user_id User id.
			 * @return void
			 */
			protected function ensure_client_directory_for_user( int $user_id ): void {}

			/**
			 * Skip PDF validation.
			 *
			 * @param array<string,mixed> $file     File array.
			 * @param string              $name     File name.
			 * @param string              $tmp_name Temp path.
			 * @return true
			 */
			protected function validate_uploaded_pdf( array $file, string $name, string $tmp_name ): bool {
				return true;
			}

			/**
			 * Returns empty string to simulate unreachable key endpoint.
			 *
			 * @param int $client_user_id Client user id.
			 * @return string
			 */
			protected function get_client_encryption_key( int $client_user_id ): string {
				return '';
			}

			/**
			 * No-op audit log — avoids wpdb dependency in tests.
			 *
			 * @param string $action         Action name.
			 * @param int    $client_user_id Client user id.
			 * @param int    $document_id    Document id.
			 * @param string $result         Result.
			 * @param string $message        Message.
			 * @param int    $actor_user_id  Actor user id.
			 * @return void
			 */
			protected function log_document_event( string $action, int $client_user_id, int $document_id, string $result, string $message, int $actor_user_id = 0 ): void {}
		};
	}
}
