<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Email;


/**
 * Test EmailTest
 * @extends TestCase
 * @final
 *
 * Test class for Email.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailTest extends TestCase
{
  //#region Constants
  /**
   * Constant VALID_EMAIL
   *
   * Valid email
   *
   * @access private
   *
   * @var string VALID_EMAIL
   */
  private const string VALID_EMAIL = 'john.doe@example.com';

  /**
   * Constant INVALID_EMAIL
   *
   * Invalid email
   *
   * @access private
   *
   * @var string INVALID_EMAIL
   */
  private const string INVALID_EMAIL = 'not-an-email';
  //#endregion

  //#region Methods
  /**
   * Method testValidEmailIsAccepted
   * @method testValidEmailIsAccepted(): void
   *
   * Test the constructor with
   * a valid email
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidEmailIsAccepted(): void
  {
    $email = new Email(value: self::VALID_EMAIL);

    self::assertSame(
      expected: self::VALID_EMAIL,
      actual: (string) $email
    );
  }

  /**
   * Method testInvalidEmailThrowsException
   * @method testInvalidEmailThrowsException(): void
   *
   * Test the constructor with
   * an invalid email
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvalidEmailThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new Email(value: self::INVALID_EMAIL);
  }
  //#endregion
}
