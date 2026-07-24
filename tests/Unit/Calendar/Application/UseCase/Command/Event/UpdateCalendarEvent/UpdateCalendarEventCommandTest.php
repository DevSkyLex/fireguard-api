<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent;

use Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\UpdateCalendarEventCommand;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test UpdateCalendarEventCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateCalendarEventCommand::class)]
final class UpdateCalendarEventCommandTest extends TestCase
{
  #[Test]
  public function itDefaultsPresenceFlagsToFalse(): void
  {
    $command = new UpdateCalendarEventCommand(
      organizationId: 'org-1',
      actorUserId: 'user-1',
      eventId: 'event-1',
      title: null,
      description: null,
      startsAt: null,
      endsAt: null,
      allDay: null,
      facilityId: null,
    );

    self::assertFalse($command->hasTitle);
    self::assertFalse($command->hasDescription);
    self::assertFalse($command->hasStartsAt);
    self::assertFalse($command->hasEndsAt);
    self::assertFalse($command->hasAllDay);
    self::assertFalse($command->hasFacilityId);
  }

  #[Test]
  public function itExposesProvidedValuesAndPresenceFlags(): void
  {
    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');
    $endsAt = new DateTimeImmutable('2026-08-01T11:00:00+02:00');

    $command = new UpdateCalendarEventCommand(
      organizationId: 'org-1',
      actorUserId: 'user-1',
      eventId: 'event-1',
      title: 'Updated drill',
      description: 'New description',
      startsAt: $startsAt,
      endsAt: $endsAt,
      allDay: true,
      facilityId: 'facility-2',
      hasTitle: true,
      hasDescription: true,
      hasStartsAt: true,
      hasEndsAt: true,
      hasAllDay: true,
      hasFacilityId: true,
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('user-1', $command->actorUserId);
    self::assertSame('event-1', $command->eventId);
    self::assertSame('Updated drill', $command->title);
    self::assertSame('New description', $command->description);
    self::assertSame($startsAt, $command->startsAt);
    self::assertSame($endsAt, $command->endsAt);
    self::assertTrue($command->allDay);
    self::assertSame('facility-2', $command->facilityId);
    self::assertTrue($command->hasTitle);
    self::assertTrue($command->hasDescription);
    self::assertTrue($command->hasStartsAt);
    self::assertTrue($command->hasEndsAt);
    self::assertTrue($command->hasAllDay);
    self::assertTrue($command->hasFacilityId);
  }
}
