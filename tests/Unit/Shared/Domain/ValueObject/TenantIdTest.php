<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\TenantId;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test TenantIdTest
 * @final
 *
 * Unit tests for the TenantId Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\TenantId
 */
final class TenantIdTest extends TestCase
{
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid TenantId can be created from a Uuid.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
    $tenantId = new TenantId($uuid);

    $this->assertEquals($uuid->value, (string) $tenantId);
  }

  /**
   * Method testFromString
   *
   * Tests that a TenantId can be created from a string.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testFromString(): void
  {
    $value = '550e8400-e29b-41d4-a716-446655440000';
    $tenantId = TenantId::fromString($value);

    $this->assertEquals($value, (string) $tenantId);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue
   *
   * Tests that creating a TenantId with an empty string throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(InvalidValueException::class);
    TenantId::fromString('');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between TenantId objects.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $t1 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
    $t2 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
    $t3 = TenantId::fromString('123e4567-e89b-12d3-a456-426614174000');

    $this->assertTrue($t1->equals($t2));
    $this->assertFalse($t1->equals($t3));
  }
}

