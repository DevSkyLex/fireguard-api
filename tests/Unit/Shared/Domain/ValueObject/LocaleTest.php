<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Locale;

/**
 * Class LocaleTest
 *
 * Unit tests for the Locale Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\Locale
 */
#[CoversClass(className: Locale::class)]
final class LocaleTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid Locale can 
   * be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = 'fr';
    $locale = new Locale($value);

    $this->assertEquals($value, $locale->value);
    $this->assertEquals($value, (string) $locale);
  }

  /**
   * Method testCannotBeCreatedWithInvalidValue
   *
   * Tests that creating a Locale with an invalid 
   * value throws an exception.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCannotBeCreatedWithInvalidValue(): void
  {
    $this->expectException(exception: InvalidValueException::class);
    new Locale(value: 'invalid-locale');
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * Locale objects.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testEquality(): void
  {
    $l1 = new Locale(value: 'fr');
    $l2 = new Locale(value: 'fr');
    $l3 = new Locale(value: 'en');

    $this->assertTrue(condition: $l1->equals($l2));
    $this->assertFalse(condition: $l1->equals($l3));
  }
  //#endregion
}

