<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride;

use Maintenance\Application\UseCase\Command\Schedule\SetMaintenanceScheduleOverride\SetMaintenanceScheduleOverrideCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\CommandMessage;

/**
 * Test SetMaintenanceScheduleOverrideCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetMaintenanceScheduleOverrideCommand::class)]
final class SetMaintenanceScheduleOverrideCommandTest extends TestCase
{
  #[Test]
  public function testRoundTripsItsProperties(): void
  {
    $command = new SetMaintenanceScheduleOverrideCommand('user-1', 'schedule-1', 'P30D');

    self::assertInstanceOf(CommandMessage::class, $command);
    self::assertSame('user-1', $command->userId);
    self::assertSame('schedule-1', $command->scheduleId);
    self::assertSame('P30D', $command->intervalOverride);
  }

  #[Test]
  public function testAllowsAClearedOverride(): void
  {
    $command = new SetMaintenanceScheduleOverrideCommand('user-1', 'schedule-1', null);

    self::assertNull($command->intervalOverride);
  }
}
