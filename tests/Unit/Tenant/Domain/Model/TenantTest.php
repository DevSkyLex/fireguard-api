<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Domain\ValueObject\TenantName;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Class TenantTest
 *
 * Unit tests for the Tenant Model.
 *
 * @category Unit Test
 * @package Tests\Unit\Tenant\Domain\Model
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Tenant::class)]
final class TenantTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanCreateTenant
   *
   * Tests that a Tenant can be created.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanCreateTenant(): void
  {
    $id = new TenantId('550e8400-e29b-41d4-a716-446655440000');
    $name = new TenantName('Acme Corporation');
    $settings = new TenantSettings();

    $tenant = Tenant::create($id, $name, $settings);

    $this->assertSame(expected: $id, actual: $tenant->id());
    $this->assertSame(expected: $name, actual: $tenant->name());
    $this->assertSame(expected: $settings, actual: $tenant->settings());
    $this->assertTrue(condition: $tenant->isActive());
  }

  /**
   * Method testCanActivateTenant
   *
   * Tests that an inactive tenant can be activated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanActivateTenant(): void
  {
    $tenant = $this->createTenant();
    $tenant->deactivate();

    $this->assertFalse(condition: $tenant->isActive());

    $tenant->activate();

    $this->assertTrue(condition: $tenant->isActive());
  }

  /**
   * Method testCanDeactivateTenant
   *
   * Tests that an active tenant can be deactivated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanDeactivateTenant(): void
  {
    $tenant = $this->createTenant();

    $this->assertTrue(condition: $tenant->isActive());

    $tenant->deactivate();

    $this->assertFalse(condition: $tenant->isActive());
  }

  /**
   * Method testCanUpdateSettings
   *
   * Tests that settings can be updated.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanUpdateSettings(): void
  {
    $tenant = $this->createTenant();
    $newSettings = new TenantSettings(
      accessTokenTtl: 7200,
      requirePkce: false,
    );

    $tenant->updateSettings($newSettings);

    $this->assertSame(expected: $newSettings, actual: $tenant->settings());
    $this->assertEquals(expected: 7200, actual: $tenant->settings()->accessTokenTtl);
    $this->assertFalse(condition: $tenant->settings()->requirePkce);
  }

  /**
   * Method testActivatingAlreadyActiveTenantIsNoOp
   *
   * Tests that activating an already active tenant does nothing.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testActivatingAlreadyActiveTenantIsNoOp(): void
  {
    $tenant = $this->createTenant();

    $this->assertTrue(condition: $tenant->isActive());

    $tenant->activate();

    $this->assertTrue(condition: $tenant->isActive());
  }

  /**
   * Method testDeactivatingAlreadyInactiveTenantIsNoOp
   *
   * Tests that deactivating an already inactive tenant does nothing.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDeactivatingAlreadyInactiveTenantIsNoOp(): void
  {
    $tenant = $this->createTenant();
    $tenant->deactivate();

    $this->assertFalse(condition: $tenant->isActive());

    $tenant->deactivate();

    $this->assertFalse(condition: $tenant->isActive());
  }

  /**
   * Method createTenant
   *
   * Helper method to create a tenant for testing.
   *
   * @access private
   *
   * @return Tenant
   */
  private function createTenant(): Tenant
  {
    return Tenant::create(
      id: new TenantId('550e8400-e29b-41d4-a716-446655440001'),
      name: new TenantName('Test Tenant'),
      settings: new TenantSettings(),
    );
  }
  //#endregion
}
