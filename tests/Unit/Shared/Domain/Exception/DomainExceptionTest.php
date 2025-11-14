<?php

declare(strict_types=1);

namespace Tests\Shared\Domain\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\DomainException;

/**
 * Test DomainExceptionText
 * @final
 *
 * Test the DomainException class
 *
 * @category Domain Exception Test
 * @package Tests\Shared\Domain\Exception
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DomainExceptionTest extends TestCase
{
  //#region Methods
  /**
   * Method testCodeReturnsUpperSnakeCaseOfExceptionClass
   *
   * Test that the code method returns the upper
   * snake case of the exception class name
   *
   * @access public
   *
   * @return void No return value
   */
  public function testCodeReturnsUpperSnakeCaseOfExceptionClass(): void
  {
    // Expected exception
    $exception = new DummyDomainException(message: 'message');

    self::assertSame(
      expected: 'DUMMY_DOMAIN_EXCEPTION',
      actual: $exception->code()
    );
  }
  //#endregion
}

/**
 * Exception DummyDomainException
 * @final
 *
 * Dummy exception for testing
 *
 * @category Domain Exception Test
 * @package Tests\Shared\Domain\Exception
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DummyDomainException extends DomainException {}
