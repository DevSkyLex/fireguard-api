<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules;

use Maintenance\Application\UseCase\Command\Sweep\RecomputeMaintenanceSchedules\RecomputeMaintenanceSchedulesCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\CommandMessage;

/**
 * Test RecomputeMaintenanceSchedulesCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecomputeMaintenanceSchedulesCommand::class)]
final class RecomputeMaintenanceSchedulesCommandTest extends TestCase
{
  #[Test]
  public function testIsAPayloadlessCommandMessage(): void
  {
    $command = new RecomputeMaintenanceSchedulesCommand();

    self::assertInstanceOf(CommandMessage::class, $command);
  }
}
