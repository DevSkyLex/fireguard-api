<?php

declare(strict_types=1);

namespace Tests\Unit\User\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Domain\ValueObject\Username;

/**
 * Test UsernameTest
 * @final
 *
 * Unit tests for the Username Value Object.
 *
 * @category ValueObject Tests
 * @package Tests\Unit\User\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Username::class)]
final class UsernameTest extends TestCase
{
  //#region Methods
  /**
   * Method testValidatesFormat
   *
   * Tests that a username validates
   * format correctly.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testValidatesFormat(): void
  {
    $username = new Username('valid_user');
    $this->assertEquals('valid_user', $username->value);
    $this->assertEquals('valid_user', (string) $username);
  }

  /**
   * Method testThrowsOnInvalidFormat
   *
   * Tests that username throws exception
   * on invalid format.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testThrowsOnInvalidFormat(): void
  {
    $this->expectException(InvalidValueException::class);
    new Username('ab'); // Too short
  }
  //#endregion
}
