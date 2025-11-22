<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test InvalidValueExceptionTest
 * @final
 *
 * Unit tests for the InvalidValueException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\Exception\InvalidValueException
 */
#[CoversClass(className: InvalidValueException::class)]
final class InvalidValueExceptionTest extends TestCase
{
  /**
   * Method testBecause
   *
   * Tests the because factory method creates an exception
   * with the provided message.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testBecause(): void
  {
    $message = 'Invalid value provided';
    $exception = InvalidValueException::because($message);

    $this->assertSame($message, $exception->getMessage());
  }
}

