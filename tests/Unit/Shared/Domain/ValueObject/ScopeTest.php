<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
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
   * Tests that creating a Scope with an empty value throws an exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new Scope(value: '');
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
