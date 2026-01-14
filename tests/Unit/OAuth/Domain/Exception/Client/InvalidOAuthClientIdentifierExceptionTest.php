<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Client;

use OAuth\Domain\Exception\Client\InvalidOAuthClientIdentifierException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class InvalidOAuthClientIdentifierExceptionTest.
 *
 * Unit tests for the InvalidOAuthClientIdentifierException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\Exception\Client\InvalidOAuthClientIdentifierException
 */
#[CoversClass(className: InvalidOAuthClientIdentifierException::class)]
final class InvalidOAuthClientIdentifierExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testExtendsInvalidValueException.
   *
   * Test that InvalidOAuthClientIdentifierException extends InvalidValueException.
   *
   * @return void no return value
   */
  #[Test]
  public function testExtendsInvalidValueException(): void
  {
    $exception = InvalidOAuthClientIdentifierException::empty();
    $this->assertInstanceOf(expected: InvalidValueException::class, actual: $exception);
  }

  /**
   * Method testInvalidPattern.
   *
   * Test the invalidPattern factory method.
   *
   * @return void no return value
   */
  #[Test]
  public function testInvalidPattern(): void
  {
    $value = 'invalid client!';
    $exception = InvalidOAuthClientIdentifierException::invalidPattern(value: $value);

    $this->assertInstanceOf(expected: InvalidOAuthClientIdentifierException::class, actual: $exception);
    $this->assertStringContainsString(needle: $value, haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: 'Invalid OAuth client identifier', haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: '3-128 characters', haystack: $exception->getMessage());
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
    $exception = InvalidOAuthClientIdentifierException::empty();

    $this->assertInstanceOf(expected: InvalidOAuthClientIdentifierException::class, actual: $exception);
    $this->assertEquals(expected: 'OAuth client identifier cannot be empty.', actual: $exception->getMessage());
  }

  // #endregion
}
