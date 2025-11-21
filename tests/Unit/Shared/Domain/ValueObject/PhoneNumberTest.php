<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\PhoneNumber;

/**
 * Class PhoneNumberTest
 *
 * Unit tests for the PhoneNumber Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\PhoneNumber
 */
final class PhoneNumberTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid PhoneNumber can 
   * be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = '+33612345678';
    $phone = new PhoneNumber(value: $value);

    $this->assertEquals(
      expected: $value,
      actual: $phone->value
    );
    $this->assertEquals(
      expected: $value,
      actual: (string) $phone
    );
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue
   *
   * Tests that creating a PhoneNumber with an invalid 
   * value throws an exception.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new PhoneNumber(value: '0612345678');
  }

  /**
   * Method testGetCountryCode
   *
   * Tests extracting the country code.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testGetCountryCode(): void
  {
    // Naive implementation grabs first 3 digits
    $phone = new PhoneNumber(value: '+33612345678');
    $this->assertEquals(
      expected: '336',
      actual: $phone->getCountryCode()
    );

    $phoneUS = new PhoneNumber(value: '+15551234567');
    $this->assertEquals(
      expected: '155',
      actual: $phoneUS->getCountryCode()
    );
  }

  /**
   * Method testGetNationalNumber
   *
   * Tests extracting the national number.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testGetNationalNumber(): void
  {
    // Naive implementation removes first 3 digits
    $phone = new PhoneNumber('+33612345678');
    $this->assertEquals('12345678', $phone->getNationalNumber());
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * PhoneNumber objects.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $p1 = new PhoneNumber(value: '+33612345678');
    $p2 = new PhoneNumber(value: '+33612345678');
    $p3 = new PhoneNumber(value: '+15551234567');

    $this->assertTrue(condition: $p1->equals($p2));
    $this->assertFalse(condition: $p1->equals($p3));
  }
  //#endregion
}

