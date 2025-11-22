<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidScopeException;
use Shared\Domain\ValueObject\Scope;

/**
 * Test ScopeTest
 * @final
 *
 * Unit tests for the Scope Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\Scope
 */
#[CoversClass(className: Scope::class)]
final class ScopeTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid Scope can be created with 
   * lowercase alphanumeric values.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'read.users';
    $scope = new Scope(value: $value);

    $this->assertEquals(expected: $value, actual: $scope->value);
    $this->assertEquals(expected: $value, actual: (string) $scope);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue
   *
   * Tests that creating a Scope with an empty 
   * value throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(exception: InvalidScopeException::class);
    $this->expectExceptionMessage(message: 'Scope cannot be empty.');
    new Scope(value: '');
  }

  /**
   * Method testCannotBeCreatedWithInvalidCharacters
   *
   * Tests that creating a Scope with invalid characters throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidCharacters(): void
  {
    $this->expectException(exception: InvalidScopeException::class);
    $this->expectExceptionMessage(message: 'Invalid scope format');
    new Scope(value: 'Invalid Scope!');
  }

  /**
   * Method testCannotBeCreatedWithUppercase
   *
   * Tests that creating a Scope with uppercase letters throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCannotBeCreatedWithUppercase(): void
  {
    $this->expectException(exception: InvalidScopeException::class);
    $this->expectExceptionMessage(message: 'Invalid scope format');
    new Scope(value: 'ReadUsers');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between Scope objects.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testEquality(): void
  {
    $s1 = new Scope(value: 'read');
    $s2 = new Scope(value: 'read');
    $s3 = new Scope(value: 'write');

    $this->assertTrue(condition: $s1->equals($s2));
    $this->assertFalse(condition: $s1->equals($s3));
  }
  //#endregion
}

