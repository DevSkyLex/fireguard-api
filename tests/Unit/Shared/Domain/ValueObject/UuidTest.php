<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

/**
 * Test UuidTest
 * @extends TestCase
 * @final
 *
 * Test class for the Uuid value object.
 *
 * @category ValueObject Tests
 * @package Tests\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UuidTest extends TestCase
{
  //#region Constants
  /**
   * Constant VALID_UUID
   *
   * Valid UUID
   *
   * @access private
   *
   * @var string VALID_UUID
   */
  private const string VALID_UUID = '123e4567-e89b-12d3-a456-426614174000';

  /**
   * Constant INVALID_UUID
   *
   * Invalid UUID
   *
   * @access private
   *
   * @var string INVALID_UUID
   */
  private const string INVALID_UUID = 'invalid-uuid';
  //#endregion

  //#region Methods
  /**
   * Method testValidUuidIsAccepted
   * @method testValidUuidIsAccepted(): void
   *
   * Test the constructor with
   * a valid UUID
   *
   * @access public
   *
   * @return void No return value
   */
  public function testValidUuidIsAccepted(): void
  {
    $uuid = new Uuid(value: self::VALID_UUID);

    self::assertSame(
      expected: self::VALID_UUID,
      actual: (string) $uuid
    );
  }

  /**
   * Method testInvalidUuidThrowsException
   * @method testInvalidUuidThrowsException(): void
   *
   * Test the constructor with
   * an invalid UUID
   *
   * @access public
   *
   * @return void No return value
   */
  public function testInvalidUuidThrowsException(): void
  {
    $this->expectException(exception: InvalidValueException::class);

    new Uuid(value: self::INVALID_UUID);
  }
  //#endregion
}
