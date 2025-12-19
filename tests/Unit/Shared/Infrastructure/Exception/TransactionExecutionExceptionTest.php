<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\TransactionExecutionException;

/**
 * Class TransactionExecutionExceptionTest.
 *
 * Unit tests for the TransactionExecutionException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Exception\TransactionExecutionException
 */
#[CoversClass(className: TransactionExecutionException::class)]
final class TransactionExecutionExceptionTest extends TestCase
{
    /**
     * Test the wrap factory method.
     */
    #[Test]
    public function testWrap(): void
    {
        $previous = new Exception('Transaction error');
        $exception = TransactionExecutionException::wrap($previous);

        $this->assertSame('Failed to execute transactional operation.', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
