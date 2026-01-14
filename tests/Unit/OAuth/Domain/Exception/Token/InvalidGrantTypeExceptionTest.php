<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Token;

use OAuth\Domain\Exception\Token\InvalidGrantTypeException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class InvalidGrantTypeExceptionTest.
 *
 * Unit tests for the InvalidGrantTypeException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\Exception\Token\InvalidGrantTypeException
 */
#[CoversClass(className: InvalidGrantTypeException::class)]
final class InvalidGrantTypeExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testExtendsInvalidValueException.
   *
   * Test that InvalidGrantTypeException extends InvalidValueException.
   *
   * @return void no return value
   */
  #[Test]
  public function testExtendsInvalidValueException(): void
  {
    $exception = InvalidGrantTypeException::empty();
    $this->assertInstanceOf(expected: InvalidValueException::class, actual: $exception);
  }

  /**
   * Method testNotAllowed.
   *
   * Test the notAllowed factory method.
   *
   * @return void no return value
   */
  #[Test]
  public function testNotAllowed(): void
  {
    $value = 'invalid_grant';
    $allowed = ['authorization_code', 'client_credentials', 'refresh_token'];
    $exception = InvalidGrantTypeException::notAllowed(value: $value, allowed: $allowed);

    $this->assertInstanceOf(expected: InvalidGrantTypeException::class, actual: $exception);
    $this->assertStringContainsString(needle: $value, haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: 'authorization_code', haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: 'client_credentials', haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: 'Allowed grant types', haystack: $exception->getMessage());
  }

  /**
   * Method testEmpty.
   *
   * Test the empty factory method.
   *
   * @return void no return value
   */
  #[Test]
  public function testEmpty(): void
  {
    $exception = InvalidGrantTypeException::empty();

    $this->assertInstanceOf(expected: InvalidGrantTypeException::class, actual: $exception);
    $this->assertEquals(expected: 'Grant type cannot be empty.', actual: $exception->getMessage());
  }

  // #endregion
}
