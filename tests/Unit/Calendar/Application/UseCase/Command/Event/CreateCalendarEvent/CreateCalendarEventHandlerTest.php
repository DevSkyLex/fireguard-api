<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\CreateCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Member\CalendarMemberDirectoryPort;
use Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\{CreateCalendarEventCommand, CreateCalendarEventHandler, CreateCalendarEventResult};
use Calendar\Domain\Event\CalendarEventCreatedEvent;
use Calendar\Domain\Exception\CalendarEventValidationException;
use Calendar\Domain\Model\Event\CalendarEvent;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, UuidGeneratorPort};

/**
 * Test CreateCalendarEventHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateCalendarEventHandler::class)]
final class CreateCalendarEventHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-1';

  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itCreatesAnEventAndDispatchesTheCreatedEvent(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->expects(self::once())->method('save')->with(self::isInstanceOf(CalendarEvent::class));

    $members = $this->createStub(CalendarMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.events.write']);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CalendarEventCreatedEvent $event): bool {
        self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
        self::assertSame('Fire drill', $event->title);

        return true;
      }));

    $handler = new CreateCalendarEventHandler(
      $repository,
      $members,
      $authorization,
      $this->uuidFactory(),
      $eventDispatcher,
    );

    $startsAt = new DateTimeImmutable('2026-08-01T09:00:00+02:00');
    $result = $handler->__invoke(new CreateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      title: 'Fire drill',
      description: 'Quarterly fire drill',
      startsAt: $startsAt,
      endsAt: null,
      allDay: false,
      facilityId: null,
    ));

    self::assertInstanceOf(CreateCalendarEventResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('Fire drill', $result->title);
    self::assertSame(self::MEMBER_ID, $result->createdByMemberId);
    self::assertEquals($startsAt, $result->startsAt);
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new CreateCalendarEventHandler(
      $repository,
      $this->createStub(CalendarMemberDirectoryPort::class),
      $authorization,
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new CreateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
    ));
  }

  #[Test]
  public function itThrowsWhenTheActingUserHasNoActiveMembership(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $members = $this->createStub(CalendarMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $handler = new CreateCalendarEventHandler(
      $repository,
      $members,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(CalendarEventValidationException::class);

    $handler->__invoke(new CreateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: null,
      allDay: false,
      facilityId: null,
    ));
  }

  #[Test]
  public function itThrowsWhenEndsAtIsBeforeStartsAt(): void
  {
    $repository = $this->createMock(CalendarEventRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $members = $this->createStub(CalendarMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(self::MEMBER_ID);

    $handler = new CreateCalendarEventHandler(
      $repository,
      $members,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->uuidFactory(),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(CalendarEventValidationException::class);

    $handler->__invoke(new CreateCalendarEventCommand(
      organizationId: self::ORGANIZATION_ID,
      actorUserId: self::USER_ID,
      title: 'Fire drill',
      description: null,
      startsAt: new DateTimeImmutable('2026-08-01T09:00:00+02:00'),
      endsAt: new DateTimeImmutable('2026-08-01T08:00:00+02:00'),
      allDay: false,
      facilityId: null,
    ));
  }

  /**
   * Method uuidFactory.
   *
   * @return UuidFactory a uuid factory generating deterministic-enough raw ids
   */
  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('018f0b68-6758-7a12-8a1d-3f0d97f64aff');

    return new UuidFactory($generator);
  }
}
