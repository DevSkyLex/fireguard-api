<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Exception;

use Maintenance\Domain\Exception\MaintenanceValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MaintenanceValidationException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceValidationException::class)]
final class MaintenanceValidationExceptionTest extends TestCase
{
  #[Test]
  public function testIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new MaintenanceValidationException('invalid interval');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('invalid interval', $exception->getMessage());
  }
}
