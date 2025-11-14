<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Locale;

/**
 * Test LocaleTest
 * @extends TestCase
 * @final
 *
 * Test class for the Locale value object.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LocaleTest extends TestCase
{
  //#region Constants
  /**
   * Constant VALID_LOCALE
   *
   * Valid locale
   *
   * @access private
   *
   * @var string VALID_LOCALE
   */
  private const string VALID_LOCALE = 'fr_FR';

  /**
   * Constant INVALID_LOCALE
   *
   * Invalid locale
   *
   * @access private
   *
   * @var string INVALID_LOCALE
   */
  private const string INVALID_LOCALE = 'french';
  //#endregion

  //#region Methods
  /**
   * Method testValidLocaleIsAccepted
   * @method testValidLocaleIsAccepted(): void
   *
   * Test the constructor with
   * a valid locale
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidLocaleIsAccepted(): void
  {
    $locale = new Locale(value: self::VALID_LOCALE);

    self::assertSame(
      expected: self::VALID_LOCALE,
      actual: (string) $locale
    );
  }

  /**
   * Method testInvalidLocaleThrowsException
   * @method testInvalidLocaleThrowsException(): void
   *
   * Test the constructor with
   * an invalid locale
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvalidLocaleThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);

    new Locale(value: self::INVALID_LOCALE);
  }
  //#endregion
}
