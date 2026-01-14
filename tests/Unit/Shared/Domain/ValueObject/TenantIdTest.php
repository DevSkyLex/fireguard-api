<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\{TenantId, Uuid};

/**
 * Test TenantIdTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\TenantId
 */
#[CoversClass(className: TenantId::class)]
final class TenantIdTest extends TestCase
{
  /**
   * Method testCanBeCreatedWithValidValue.
   *
   * Tests that a valid TenantId can be created from a Uuid.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $uuid = new Uuid('550e8400-e29b-41d4-a716-446655440000');
    $tenantId = new TenantId($uuid);

    $this->assertEquals($uuid->value, (string) $tenantId);
  }

  /**
   * Method testFromString.
   *
   * Tests that a TenantId can be created from a string.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFromString(): void
  {
    $value = '550e8400-e29b-41d4-a716-446655440000';
    $tenantId = TenantId::fromString($value);

    $this->assertEquals($value, (string) $tenantId);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue.
   *
   * Tests that creating a TenantId with an empty string throws an exception.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(InvalidValueException::class);
    TenantId::fromString('');
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between TenantId objects.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $t1 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
    $t2 = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');
    $t3 = TenantId::fromString('123e4567-e89b-12d3-a456-426614174000');

    $this->assertTrue($t1->equals($t2));
    $this->assertFalse($t1->equals($t3));
  }
}
