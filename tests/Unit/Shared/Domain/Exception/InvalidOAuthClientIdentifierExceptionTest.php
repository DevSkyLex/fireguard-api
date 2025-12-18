<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use OAuth\Domain\Exception\InvalidOAuthClientIdentifierException;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class InvalidOAuthClientIdentifierExceptionTest
 *
 * Unit tests for the InvalidOAuthClientIdentifierException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\Exception
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \OAuth\Domain\Exception\InvalidOAuthClientIdentifierException
 */
#[CoversClass(className: InvalidOAuthClientIdentifierException::class)]
final class InvalidOAuthClientIdentifierExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testExtendsInvalidValueException
   *
   * Test that InvalidOAuthClientIdentifierException extends InvalidValueException.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testExtendsInvalidValueException(): void
  {
    $exception = InvalidOAuthClientIdentifierException::empty();
    $this->assertInstanceOf(expected: InvalidValueException::class, actual: $exception);
  }

  /**
   * Method testInvalidPattern
   *
   * Test the invalidPattern factory method.
   * 
   * @access public
   * 
   * @return void No return value.
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
   * Method testEmpty
   *
   * Test the empty factory method.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testEmpty(): void
  {
    $exception = InvalidOAuthClientIdentifierException::empty();

    $this->assertInstanceOf(expected: InvalidOAuthClientIdentifierException::class, actual: $exception);
    $this->assertEquals(expected: 'OAuth client identifier cannot be empty.', actual: $exception->getMessage());
  }

  //#endregion
}

