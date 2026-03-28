<?php
/**
 * Module manager tests.
 *
 * @package UplinkPortal\Tests
 */

namespace UplinkPortal\Tests;

use PHPUnit\Framework\TestCase;
use UplinkPortal\Core\Modules\Core\FinanceModule;
use UplinkPortal\Core\Modules\Core\ItSupportModule;
use UplinkPortal\Core\Modules\Core\ProjectsModule;
use UplinkPortal\Core\Modules\Custom\DummyCustomModule;
use UplinkPortal\Core\Modules\Custom\SecureSampleCustomModule;

/**
 * Basic tests for public scaffold classes.
 */
class ModuleManagerTest extends TestCase {
	/**
	 * Ensures finance module exposes required metadata.
	 *
	 * @return void
	 */
	public function test_finance_module_id_is_stable(): void {
		$module = new FinanceModule();

		$this->assertSame( 'finance', $module->id() );
		$this->assertNotEmpty( $module->label() );
	}

	/**
	 * Ensures IT support and projects module ids are stable.
	 *
	 * @return void
	 */
	public function test_core_module_ids_are_stable(): void {
		$this->assertSame( 'it_support', ( new ItSupportModule() )->id() );
		$this->assertSame( 'projects', ( new ProjectsModule() )->id() );
	}

	/**
	 * Ensures dummy custom module id is stable.
	 *
	 * @return void
	 */
	public function test_dummy_custom_module_id_is_stable(): void {
		$this->assertSame( 'custom_dummy', ( new DummyCustomModule() )->id() );
	}

	/**
	 * Ensures secure sample custom module id is stable.
	 *
	 * @return void
	 */
	public function test_secure_sample_custom_module_id_is_stable(): void {
		$this->assertSame( 'custom_secure_sample', ( new SecureSampleCustomModule() )->id() );
	}
}
