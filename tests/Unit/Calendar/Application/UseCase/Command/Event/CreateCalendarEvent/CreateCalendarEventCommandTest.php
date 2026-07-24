<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\CreateCalendarEvent;

use Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\CreateCalendarEventCommand;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CreateCalendarEventCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateCalendarEventCommand::class)]
final class CreateCalendarEventCommandTest extends TestCase
{
  #[Test]
  public function itExposesConstructorArguments(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');
    $endsAt = new DateTimeImmutable('2026-08-01T11:00:00+02:00');

    $command = new CreateCalendarEventCommand(
      organizationId: 'org-1',
      actorUserId: 'user-1',
      title: 'Fire drill',
      description: 'Quarterly exercise',
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: false,
      facilityId: 'facility-1',
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('Fire drill', $command->title);
    self::assertSame('Quarterly exercise', $command->description);
    self::assertSame($startsAt, $command->startsAt);
    self::assertSame($endsAt, $command->endsAt);
    self::assertFalse($command->allDay);
    self::assertSame('facility-1', $command->facilityId);
  }
}
