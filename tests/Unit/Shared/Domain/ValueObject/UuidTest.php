<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test UuidTest
 * @final
 *
 * Unit tests for the Uuid Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\Uuid
 */
final class UuidTest extends TestCase
{
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid UUID can be created.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = '550e8400-e29b-41d4-a716-446655440000';
    $uuid = new Uuid($value);

    $this->assertEquals($value, $uuid->value);
    $this->assertEquals($value, (string) $uuid);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue
   *
   * Tests that creating a UUID with an invalid format throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(InvalidValueException::class);
    new Uuid('invalid-uuid');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between Uuid objects.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $u1 = new Uuid('550e8400-e29b-41d4-a716-446655440000');
    $u2 = new Uuid('550e8400-e29b-41d4-a716-446655440000');
    $u3 = new Uuid('123e4567-e89b-12d3-a456-426614174000');

    $this->assertTrue($u1->equals($u2));
    $this->assertFalse($u1->equals($u3));
  }

  /**
   * Method testGenerate
   *
   * Tests the generate factory method.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testGenerate(): void
  {
    $uuid = Uuid::generate();
    $this->assertInstanceOf(Uuid::class, $uuid);
  }
}

