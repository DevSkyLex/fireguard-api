<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Domain\Exception;

use Automation\Domain\Exception\AutomationRunNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test AutomationRunNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationRunNotFoundException::class)]
final class AutomationRunNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsAMessageFromTheId(): void
  {
    $exception = AutomationRunNotFoundException::withId('run-123');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Automation run with ID "run-123" not found.', $exception->getMessage());
  }
}
