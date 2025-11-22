<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidScopeException;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class InvalidScopeExceptionTest
 *
 * Unit tests for the InvalidScopeException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\Exception
 * 
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\Exception\InvalidScopeException
 */
#[CoversClass(className: InvalidScopeException::class)]
final class InvalidScopeExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testExtendsInvalidValueException
   *
   * Test that InvalidScopeException extends InvalidValueException.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testExtendsInvalidValueException(): void
  {
    $exception = InvalidScopeException::empty();
    $this->assertInstanceOf(expected: InvalidValueException::class, actual: $exception);
  }

  /**
   * Method testInvalidFormat
   *
   * Test the invalidFormat factory method.
   * 
   * @access public
   * 
   * @return void No return value.
   */
  #[Test]
  public function testInvalidFormat(): void
  {
    $value = 'Invalid Scope!';
    $exception = InvalidScopeException::invalidFormat(value: $value);

    $this->assertInstanceOf(expected: InvalidScopeException::class, actual: $exception);
    $this->assertStringContainsString(needle: $value, haystack: $exception->getMessage());
    $this->assertStringContainsString(needle: 'Invalid scope format', haystack: $exception->getMessage());
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
    $exception = InvalidScopeException::empty();

    $this->assertInstanceOf(expected: InvalidScopeException::class, actual: $exception);
    $this->assertEquals(expected: 'Scope cannot be empty.', actual: $exception->getMessage());
  }

  //#endregion
}

