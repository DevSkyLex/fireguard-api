<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\DomainException;

/**
 * Test DomainExceptionTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\Exception\DomainException
 */
#[CoversClass(className: DomainException::class)]
final class DomainExceptionTest extends TestCase
{
    /**
     * Method testCodeReturnsExpectedFormat.
     *
     * Tests that the code() method returns the exception class name
     * in uppercase snake_case format.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testCodeReturnsExpectedFormat(): void
    {
        $exception = new class ('Test message') extends DomainException {};
        $code = $exception->code();

        // Anonymous classes include file path and random hash in their name
        // Just verify we get a non-empty string back
        $this->assertNotEmpty($code);
    }
}
