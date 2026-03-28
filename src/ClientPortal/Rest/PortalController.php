<?php
/**
 * REST controller.
 *
 * @package UplinkPortal\ClientPortal\Rest
 */

namespace UplinkPortal\ClientPortal\Rest;

use UplinkPortal\Core\Integrations\DummyBillingConnector;
use UplinkPortal\Core\Integrations\DummySubscriptionConnector;
use UplinkPortal\Core\Integrations\DummyTicketingConnector;
use UplinkPortal\Core\Modules\ModuleManager;
use UplinkPortal\Core\Support\ClientProfileStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Registers core REST routes.
 */
class PortalController {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'uplinkportal/v1';

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Client profile store.
	 *
	 * @var ClientProfileStore
	 */
	private ClientProfileStore $client_profiles;

	/**
	 * Constructor.
	 *
	 * @param ModuleManager      $module_manager Module manager.
	 * @param ClientProfileStore $client_profiles Client profile store.
	 */
	public function __construct( ModuleManager $module_manager, ClientProfileStore $client_profiles ) {
		$this->module_manager  = $module_manager;
		$this->client_profiles = $client_profiles;
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'check_health_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_me' ),
				'permission_callback' => array( $this, 'check_portal_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/modules',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_modules' ),
				'permission_callback' => array( $this, 'check_portal_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/data/(?P<module>[a-z0-9_\\-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_module_data' ),
				'permission_callback' => array( $this, 'check_portal_access' ),
				'args'                => array(
					'module' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/client-profile',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_client_profile' ),
					'permission_callback' => array( $this, 'check_client_profile_access' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_client_profile' ),
					'permission_callback' => array( $this, 'check_client_profile_update_access' ),
					'args'                => array(
						'company_name'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'required'          => false,
						),
						'department'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'required'          => false,
						),
						'title'         => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'required'          => false,
						),
						'phone_number'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'required'          => false,
						),
						'email_address' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_email',
							'required'          => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the health endpoint (MSP operators only).
	 *
	 * @since 0.2.0
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_health_access( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'uplinkportal_unauthenticated', __( 'Authentication required.', 'uplinkportal' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'msp_manage' ) ) {
			return new WP_Error( 'uplinkportal_forbidden', __( 'You are not allowed to access health information.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		if ( ! $this->is_rest_nonce_valid( $request ) ) {
			return new WP_Error( 'uplinkportal_invalid_nonce', __( 'Invalid or missing REST nonce.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Returns plugin health status and version for observability consumers.
	 *
	 * The response intentionally contains no secrets or environment details.
	 * A per-request ID is included so errors can be correlated in logs without
	 * exposing internal state.
	 *
	 * @since 0.2.0
	 * @return WP_REST_Response
	 */
	public function get_health(): WP_REST_Response {
		$request_id = wp_generate_uuid4();

		$data = array(
			'status'     => 'ok',
			'version'    => UPLINKPORTAL_VERSION,
			'request_id' => $request_id,
		);

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		$response->header( 'X-UplinkPortal-Request-Id', $request_id );

		return $response;
	}

	/**
	 * Permission callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_portal_access( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'uplinkportal_unauthenticated', __( 'Authentication required.', 'uplinkportal' ), array( 'status' => 401 ) );
		}

		if ( ! current_user_can( 'client_access' ) ) {
			return new WP_Error( 'uplinkportal_forbidden', __( 'You are not allowed to access this portal.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		if ( ! $this->is_rest_nonce_valid( $request ) ) {
			return new WP_Error( 'uplinkportal_invalid_nonce', __( 'Invalid or missing REST nonce.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Permission callback for reading current client profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_client_profile_access( WP_REST_Request $request ) {
		$allowed = $this->check_portal_access( $request );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$user_id = get_current_user_id();
		if ( ! $this->client_profiles->can_current_user_access_profile( $user_id, false ) ) {
			return new WP_Error( 'uplinkportal_client_profile_forbidden', __( 'You are not allowed to read this client profile.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Permission callback for updating current client profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function check_client_profile_update_access( WP_REST_Request $request ) {
		$allowed = $this->check_portal_access( $request );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$user_id = get_current_user_id();
		if ( ! $this->client_profiles->can_current_user_access_profile( $user_id, true ) ) {
			return new WP_Error( 'uplinkportal_client_profile_update_forbidden', __( 'You are not allowed to update this client profile.', 'uplinkportal' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Returns current user data.
	 *
	 * @return WP_REST_Response
	 */
	public function get_me(): WP_REST_Response {
		$user = wp_get_current_user();
		$data = array(
			'id'           => (int) $user->ID,
			'display_name' => $user->display_name,
		);

		$expose_email = false;
		/**
		 * Filters whether current-user email is exposed in /me response.
		 *
		 * @param bool $expose_email Whether email is exposed.
		 */
		$expose_email = (bool) apply_filters( 'uplinkportal_rest_expose_email', $expose_email );
		if ( $expose_email ) {
			$data['email'] = $user->user_email;
		}

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Returns enabled modules.
	 *
	 * @return WP_REST_Response
	 */
	public function get_modules(): WP_REST_Response {
		$payload = array();

		foreach ( $this->module_manager->enabled() as $module ) {
			$payload[] = array(
				'id'    => $module->id(),
				'label' => $module->label(),
			);
		}

		$response = new WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Returns example module data.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_module_data( WP_REST_Request $request ) {
		$module = sanitize_key( (string) $request->get_param( 'module' ) );
		$user   = get_current_user_id();

		switch ( $module ) {
			case 'tickets':
				if ( ! current_user_can( 'client_view_tickets' ) ) {
					return new WP_Error( 'uplinkportal_forbidden', __( 'You are not allowed to view tickets.', 'uplinkportal' ), array( 'status' => 403 ) );
				}
				$data = ( new DummyTicketingConnector() )->get_tickets_for_user( $user );
				break;
			case 'billing':
				if ( ! current_user_can( 'client_view_billing' ) ) {
					return new WP_Error( 'uplinkportal_forbidden', __( 'You are not allowed to view billing data.', 'uplinkportal' ), array( 'status' => 403 ) );
				}
				$data = ( new DummyBillingConnector() )->get_invoices_for_user( $user );
				break;
			case 'subscriptions':
				if ( ! current_user_can( 'client_view_subscriptions' ) ) {
					return new WP_Error( 'uplinkportal_forbidden', __( 'You are not allowed to view subscriptions.', 'uplinkportal' ), array( 'status' => 403 ) );
				}
				$data = ( new DummySubscriptionConnector() )->get_subscriptions_for_user( $user );
				break;
			default:
				return new WP_Error( 'uplinkportal_not_found', __( 'Module data provider not found.', 'uplinkportal' ), array( 'status' => 404 ) );
		}

		$response = new WP_REST_Response(
			array(
				'module' => $module,
				'data'   => $data,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Returns the current user client profile payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_client_profile(): WP_REST_Response {
		$user_id = get_current_user_id();
		$profile = $this->client_profiles->get_profile_for_user( $user_id );
		$schema  = $this->client_profiles->get_profile_schema();

		$response = new WP_REST_Response(
			array(
				'user_id'  => $user_id,
				'provider' => $this->client_profiles->get_default_provider(),
				'profile'  => $profile,
				'schema'   => $schema,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Updates the current user client profile payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_client_profile( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$schema  = array_keys( $this->client_profiles->get_profile_schema() );
		$payload = array();

		foreach ( $schema as $field_key ) {
			$payload[ $field_key ] = $request->get_param( $field_key );
		}

		$updated = $this->client_profiles->update_profile_for_user( $user_id, $payload );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$response = new WP_REST_Response(
			array(
				'user_id' => $user_id,
				'profile' => $updated,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );

		return $response;
	}

	/**
	 * Validates REST nonce for cookie-authenticated requests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	private function is_rest_nonce_valid( WP_REST_Request $request ): bool {
		$enforce_nonce = (bool) apply_filters( 'uplinkportal_enforce_rest_nonce', true );
		if ( ! $enforce_nonce ) {
			return true;
		}

		if ( '' !== (string) $request->get_header( 'Authorization' ) ) {
			// Allow non-cookie authentication workflows (e.g. Application Passwords, service tokens, external systems).
			return true;
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( '' === $nonce ) {
			return false;
		}

		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}
}
