<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\MessengerRuntimeException;

/**
 * Class MessengerRuntimeExceptionTest.
 *
 * Unit tests for the MessengerRuntimeException.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Exception\MessengerRuntimeException
 */
#[CoversClass(className: MessengerRuntimeException::class)]
final class MessengerRuntimeExceptionTest extends TestCase
{
    /**
     * Test the wrap factory method.
     */
    #[Test]
    public function testWrap(): void
    {
        $previous = new Exception('Original error');
        $exception = MessengerRuntimeException::wrap($previous);

        $this->assertSame('Original error', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
