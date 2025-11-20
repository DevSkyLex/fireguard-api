<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\TransactionExecutionException;
use Exception;

/**
 * Class TransactionExecutionExceptionTest
 *
 * Unit tests for the TransactionExecutionException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\TransactionExecutionException
 */
final class TransactionExecutionExceptionTest extends TestCase
{
  /**
   * Test the wrap factory method.
   */
  public function testWrap(): void
  {
    $previous = new Exception('Transaction error');
    $exception = TransactionExecutionException::wrap($previous);

    $this->assertSame('Failed to execute transactional operation.', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}
