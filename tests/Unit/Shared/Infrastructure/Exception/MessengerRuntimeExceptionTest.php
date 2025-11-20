<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Exception;

/**
 * Class MessengerRuntimeExceptionTest
 *
 * Unit tests for the MessengerRuntimeException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\MessengerRuntimeException
 */
final class MessengerRuntimeExceptionTest extends TestCase
{
  /**
   * Test the wrap factory method.
   */
  public function testWrap(): void
  {
    $previous = new Exception('Original error');
    $exception = MessengerRuntimeException::wrap($previous);

    $this->assertSame('Original error', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}
