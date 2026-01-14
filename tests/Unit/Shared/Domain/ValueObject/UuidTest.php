<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test UuidTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\Uuid
 */
#[CoversClass(className: Uuid::class)]
final class UuidTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidValue.
   *
   * Tests that a valid UUID can be created.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = '550e8400-e29b-41d4-a716-446655440000';
    $uuid = new Uuid($value);

    $this->assertEquals($value, $uuid->value);
    $this->assertEquals($value, (string) $uuid);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue.
   *
   * Tests that creating a UUID with an invalid format throws an exception.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new Uuid(value: 'invalid-uuid');
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between Uuid objects.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $u1 = new Uuid(value: '550e8400-e29b-41d4-a716-446655440000');
    $u2 = new Uuid(value: '550e8400-e29b-41d4-a716-446655440000');
    $u3 = new Uuid(value: '123e4567-e89b-12d3-a456-426614174000');

    $this->assertTrue(condition: $u1->equals($u2));
    $this->assertFalse(condition: $u1->equals($u3));
  }

  // #endregion
}
