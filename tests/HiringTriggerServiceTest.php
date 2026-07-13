<?php
/**
 * Hiring trigger service tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Support\ClientProfileStore;
use UplinkPortal\Core\Support\HiringTriggerService;

/**
 * Tests for HiringTriggerService gate metrics and status logic.
 */
class HiringTriggerServiceTest extends TestCase {
	/**
	 * Resets stub option and filter state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['uplinkportal_test_wp_options'] = array();
		$GLOBALS['uplinkportal_test_filters']    = array();
	}

	/**
	 * Builds a service backed by a mocked profile store.
	 *
	 * @param array<int,array<string,string>> $profiles Profiles keyed by user ID.
	 * @return HiringTriggerService
	 */
	private function make_service( array $profiles ): HiringTriggerService {
		$store = $this->createMock( ClientProfileStore::class );

		$users = array();
		foreach ( array_keys( $profiles ) as $user_id ) {
			$user     = new \stdClass();
			$user->ID = $user_id;
			$users[]  = $user;
		}

		$store->method( 'get_client_workspace_users' )->willReturn( $users );
		$store->method( 'get_profile_for_user' )->willReturnCallback(
			static fn( int $user_id ): array => $profiles[ $user_id ] ?? array()
		);

		return new HiringTriggerService( $store );
	}

	/**
	 * Ensures only active recurring clients are counted.
	 *
	 * @return void
	 */
	public function test_recurring_count_filters_type_and_status(): void {
		$service = $this->make_service(
			array(
				1 => array(
					'contract_type'   => 'recurring',
					'contract_status' => 'active',
				),
				2 => array(
					'contract_type'   => 'recurring',
					'contract_status' => 'lapsed',
				),
				3 => array(
					'contract_type'   => 'project',
					'contract_status' => 'active',
				),
				4 => array(),
			)
		);

		$this->assertSame( 1, $service->get_recurring_client_count() );
	}

	/**
	 * Ensures MRR defaults to zero and clamps invalid filter values.
	 *
	 * @return void
	 */
	public function test_mrr_defaults_to_zero_and_sanitizes_filter(): void {
		$service = $this->make_service( array() );

		$this->assertSame( 0.0, $service->get_current_mrr() );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_hiring_trigger_mrr'] = static fn() => '2500.5';
		$this->assertSame( 2500.5, $service->get_current_mrr() );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_hiring_trigger_mrr'] = static fn() => -50;
		$this->assertSame( 0.0, $service->get_current_mrr() );
	}

	/**
	 * Ensures gate status transitions across not_met, approaching, and met.
	 *
	 * @return void
	 */
	public function test_gate_status_transitions_on_client_count(): void {
		$active = array(
			'contract_type'   => 'recurring',
			'contract_status' => 'active',
		);

		$this->assertSame( 'not_met', $this->make_service( array( 1 => $active ) )->get_gate_status() );

		$this->assertSame(
			'approaching',
			$this->make_service( array_fill( 1, 3, $active ) )->get_gate_status()
		);

		$this->assertSame(
			'met',
			$this->make_service( array_fill( 1, 5, $active ) )->get_gate_status()
		);
	}

	/**
	 * Ensures MRR at target marks the month sustained and meets the gate.
	 *
	 * @return void
	 */
	public function test_gate_met_when_mrr_sustained_this_month(): void {
		$service = $this->make_service( array() );

		$GLOBALS['uplinkportal_test_filters']['uplinkportal_hiring_trigger_mrr'] = static fn() => 10000.0;

		$this->assertSame( 'met', $service->get_gate_status() );
		$this->assertSame(
			gmdate( 'Y-m' ),
			$GLOBALS['uplinkportal_test_wp_options']['uplinkportal_mrr_sustained_month']
		);
	}

	/**
	 * Ensures a sustained month recorded for a prior month does not meet the gate.
	 *
	 * @return void
	 */
	public function test_stale_sustained_month_does_not_meet_gate(): void {
		$service = $this->make_service( array() );

		$GLOBALS['uplinkportal_test_wp_options']['uplinkportal_mrr_sustained_month'] = '2020-01';

		$this->assertSame( 'not_met', $service->get_gate_status() );
	}
}
