<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\{DeleteCalendarEventCommand, DeleteCalendarEventHandler};
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Test DeleteCalendarEventHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCalendarEventHandler::class)]
final class DeleteCalendarEventHandlerTest extends TestCase
{
  private const string EVENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itRemovesTheEventAndDispatchesTheDeletedEvent(): void
  {
    $event = $this->event();

    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($event);
    $repository->expects(self::once())->method('remove')->with($event);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', self::ORGANIZATION_ID, ['organization.events.write']);

    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch');

    $handler = new DeleteCalendarEventHandler($repository, $authorization, $dispatcher);
    $result = $handler(new DeleteCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
    ));

    self::assertSame(self::EVENT_ID, $result->eventId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function itThrowsWhenTheEventDoesNotExist(): void
  {
    $repository = $this->createStub(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $dispatcher = $this->createStub(EventDispatcherPort::class);

    $handler = new DeleteCalendarEventHandler($repository, $authorization, $dispatcher);

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new DeleteCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
      eventId: self::EVENT_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenTheEventBelongsToAnotherOrganization(): void
  {
    $repository = $this->createStub(CalendarEventRepositoryPort::class);
    $repository->method('findById')->willReturn($this->event('018f0b68-6758-7a12-8a1d-3f0d97f64a09'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $dispatcher = $this->createStub(EventDispatcherPort::class);

    $handler = new DeleteCalendarEventHandler($repository, $authorization, $dispatcher);

    $this->expectException(CalendarEventNotFoundException::class);

    $handler(new DeleteCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: 'user-1',
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
