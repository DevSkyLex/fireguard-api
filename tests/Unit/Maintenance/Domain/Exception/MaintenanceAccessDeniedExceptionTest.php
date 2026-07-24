<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Exception;

use Maintenance\Domain\Exception\MaintenanceAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MaintenanceAccessDeniedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceAccessDeniedException::class)]
final class MaintenanceAccessDeniedExceptionTest extends TestCase
{
  #[Test]
  public function testIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new MaintenanceAccessDeniedException('denied');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('denied', $exception->getMessage());
  }
}
