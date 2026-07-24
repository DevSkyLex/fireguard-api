<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Event\GetCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\{GetCalendarEventHandler, GetCalendarEventQuery};
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCalendarEventHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarEventHandler::class)]
final class GetCalendarEventHandlerTest extends TestCase
{
  private const string EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itReturnsTheEventForTheOwningOrganization(): void
  {
    $repository = $this->createStub(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($this->event());

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', self::ORGANIZATION_ID, ['organization.events.read']);

    $handler = new GetCalendarEventHandler($repository, $authorization);
    $result = $handler(new GetCalendarEventQuery(
      userId: 'user-1',
      organizationId: self::ORGANIZATION_ID,
      eventId: self::EVENT_ID,
    ));

    self::assertSame(self::EVENT_ID, $result->id);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Fire drill', $result->title);
  }

  #[Test]
  public function itThrowsWhenTheEventDoesNotExist(): void
  {
    $repository = $this->createStub(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new GetCalendarEventHandler($repository, $this->createStub(OrganizationAuthorizationPort::class));

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new GetCalendarEventQuery(
      userId: 'user-1',
      organizationId: self::ORGANIZATION_ID,
      eventId: self::EVENT_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheEventBelongsToAnotherOrganization(): void
  {
    $repository = $this->createStub(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($this->event('018f0b68-6758-7a12-8a1d-3f0d97f64a09'));

    $handler = new GetCalendarEventHandler($repository, $this->createStub(OrganizationAuthorizationPort::class));

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new GetCalendarEventQuery(
      userId: 'user-1',
      organizationId: self::ORGANIZATION_ID,
      eventId: self::EVENT_ID,
    ));
  }

  private function event(string $organizationId = self::ORGANIZATION_ID): CalendarEvent
  {
    return CalendarEvent::create(
      id: CalendarEventId::fromString(self::EVENT_ID),
      organizationId: $organizationId,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
      createdByMemberId: 'member-1',
    );
  }
}
