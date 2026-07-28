<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\{UpdateCalendarEventCommand, UpdateCalendarEventHandler};
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateCalendarEventHandler::class)]
final class UpdateCalendarEventHandlerTest extends TestCase
{
  private const string EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itClearsExplicitlyProvidedNullableFields(): void
  {
    $event = $this->event();
    $repository = $this->repositoryFor($event);

    $handler = new UpdateCalendarEventHandler($repository, $this->authorization());
    $result = $handler(new UpdateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
      title: null,
      description: null,
      startsAt: null,
      endsAt: null,
      allDay: null,
      facilityId: null,
      hasDescription: true,
      hasEndsAt: true,
      hasFacilityId: true,
    ));

    self::assertSame('Fire drill', $result->title);
    self::assertNull($result->description);
    self::assertNull($result->endsAt);
    self::assertNull($result->facilityId);
  }

  #[Test]
  public function itPreservesOmittedFieldsAndUpdatesProvidedValues(): void
  {
    $event = $this->event();
    $repository = $this->repositoryFor($event);
    $newStart = new DateTimeImmutable('2026-08-01T10:00:00+02:00');

    $handler = new UpdateCalendarEventHandler($repository, $this->authorization());
    $result = $handler(new UpdateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
      title: 'Updated drill',
      description: null,
      startsAt: $newStart,
      endsAt: null,
      allDay: true,
      facilityId: null,
      hasTitle: true,
      hasStartsAt: true,
      hasAllDay: true,
    ));

    self::assertSame('Updated drill', $result->title);
    self::assertSame('Quarterly exercise', $result->description);
    self::assertEquals($newStart, $result->startsAt);
    self::assertEquals(new DateTimeImmutable('2026-08-01T11:00:00+02:00'), $result->endsAt);
    self::assertTrue($result->allDay);
    self::assertSame('facility-1', $result->facilityId);
  }

  #[Test]
  public function itThrowsWhenTheEventDoesNotExist(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn(null);
    $repository->expects(self::never())->method('save');

    $handler = new UpdateCalendarEventHandler($repository, $this->authorization());

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new UpdateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
      title: null,
      description: null,
      startsAt: null,
      endsAt: null,
      allDay: null,
      facilityId: null,
    ));
  }

  #[Test]
  public function itThrowsWhenTheEventBelongsToAnotherOrganization(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($this->event());
    $repository->expects(self::never())->method('save');

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('assertGrantedPermissions');

    $handler = new UpdateCalendarEventHandler($repository, $authorization);

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new UpdateCalendarEventCommand(
      organizationId: '018f0b68-6758-7a12-8a1d-3f0d97f64a99',
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
      title: null,
      description: null,
      startsAt: null,
      endsAt: null,
      allDay: null,
      facilityId: null,
    ));
  }

  private function event(): CalendarEvent
  {
    return CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: self::ORGANIZATION_ID,
      title: 'Fire drill',
      description: 'Quarterly exercise',
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: new DateTimeImmutable('2026-08-01T11:00:00+02:00'),
      allDay: false,
      facilityId: 'facility-1',
      createdByMemberId: 'member-1',
    );
  }

  private function repositoryFor(CalendarEvent $event): CalendarEventRepositoryPort
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($event);
    $repository->expects(self::once())->method('save')->with($event);

    return $repository;
  }

  private function authorization(): OrganizationAuthorizationPort
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', self::ORGANIZATION_ID, ['organization.events.write']);

    return $authorization;
  }
}
