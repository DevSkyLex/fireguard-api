<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Domain\Exception;

use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ComplianceAccessDeniedExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceAccessDeniedException::class)]
final class ComplianceAccessDeniedExceptionTest extends TestCase
{
  #[Test]
  public function testItIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new ComplianceAccessDeniedException('forbidden');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('forbidden', $exception->getMessage());
  }

  #[Test]
  public function testItPreservesAPreviousException(): void
  {
    $previous = new RuntimeException('root cause');
    $exception = new ComplianceAccessDeniedException('forbidden', 0, $previous);

    self::assertSame($previous, $exception->getPrevious());
  }
}
