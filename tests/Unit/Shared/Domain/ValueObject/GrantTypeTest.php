<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\GrantType;

/**
 * Class GrantTypeTest
 *
 * Unit tests for the GrantType Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Domain\ValueObject\GrantType
 */
final class GrantTypeTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid GrantType can be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'authorization_code';
    $grantType = new GrantType(value: $value);

    $this->assertEquals(expected: $value, actual: $grantType->value);
    $this->assertEquals(expected: $value, actual: (string) $grantType);
  }

  /**
   * Method testCannotBeCreatedWithEmptyValue
   *
   * Tests that creating a GrantType with an 
   * empty value throws an exception.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithEmptyValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new GrantType(value: '');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * GrantType objects.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $g1 = new GrantType(value: 'client_credentials');
    $g2 = new GrantType(value: 'client_credentials');
    $g3 = new GrantType(value: 'password');

    $this->assertTrue(condition: $g1->equals($g2));
    $this->assertFalse(condition: $g1->equals($g3));
  }
  //#endregion
}
