<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TenantId;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test TenantIdTest
 * @final
 *
 * Test class for the TenantId
 * value object.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantIdTest extends TestCase
{
  //#region Constants
  /**
   * Constant UUID_VALUE
   *
   * UUID value
   *
   * @access private
   *
   * @var string UUID_VALUE
   */
  private const string UUID_VALUE = '123e4567-e89b-12d3-a456-426614174000';
  //#endregion

  //#region Methods
  /**
   * Method testTenantIdWrapsUuid
   *
   * Test the constructor with
   * a valid UUID
   *
   * @access public
   *
   * @return void No return value
   */
  public function testTenantIdWrapsUuid(): void
  {
    $uuid = new Uuid(value: self::UUID_VALUE);
    $tenantId = new TenantId(uuid: $uuid);

    self::assertSame(expected: self::UUID_VALUE, actual: (string) $tenantId);
    self::assertTrue(condition: $tenantId->equals(new TenantId(uuid: $uuid)));
    self::assertSame(expected: $uuid, actual: $tenantId->toUuid());
  }
  //#endregion
}
