<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\Exception;

use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MaintenanceNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceNotFoundException::class)]
final class MaintenanceNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsADescriptiveMessage(): void
  {
    $exception = MaintenanceNotFoundException::withId('schedule-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Maintenance schedule with ID "schedule-42" not found.', $exception->getMessage());
  }
}
