<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DeleteCalendarEventCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCalendarEventCommand::class)]
final class DeleteCalendarEventCommandTest extends TestCase
{
  #[Test]
  public function itExposesConstructorArguments(): void
  {
    $command = new DeleteCalendarEventCommand(
      organizationId: 'org-1',
      actorUserId: 'user-1',
      eventId: 'event-1',
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('event-1', $command->eventId);
  }
}
