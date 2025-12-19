<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\BusinessRuleViolationException;

/**
 * Test BusinessRuleViolationExceptionTest.
 *
 * @category Unit Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\Exception\BusinessRuleViolationException
 */
#[CoversClass(className: BusinessRuleViolationException::class)]
final class BusinessRuleViolationExceptionTest extends TestCase
{
    /**
     * Method testBecause.
     *
     * Tests the because factory method creates an exception
     * with the expected message format.
     *
     * @since 1.0.0
     *
     * @return void no return value
     */
    #[Test]
    public function testBecause(): void
    {
        $rule = 'User must be active';
        $exception = BusinessRuleViolationException::because($rule);

        $this->assertSame('Business rule violated: User must be active', $exception->getMessage());
    }
}
