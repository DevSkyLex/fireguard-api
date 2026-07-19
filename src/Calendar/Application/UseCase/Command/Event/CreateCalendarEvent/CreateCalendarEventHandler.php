<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\CreateCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Member\CalendarMemberDirectoryPort;
use Calendar\Domain\Event\CalendarEventCreatedEvent;
use Calendar\Domain\Exception\CalendarEventValidationException;
use Calendar\Domain\Model\Event\CalendarEvent;
use Calendar\Domain\ValueObject\CalendarEventId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase CreateCalendarEventHandler.
 *
 * Self-enforces `organization.events.write` and resolves the acting user's
 * organization member id (the event's `createdByMemberId`) through
 * {@see CalendarMemberDirectoryPort} — never trusting a client-supplied
 * member id.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateCalendarEventHandler implements CommandHandler
{
  // #region Constants
  private const string WRITE_PERMISSION = 'organization.events.write';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CalendarEventRepositoryPort $repository the calendar event repository port
   * @param CalendarMemberDirectoryPort $members the cross-module member directory port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param UuidFactory $uuidFactory the uuid factory
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private CalendarEventRepositoryPort $repository,
    private CalendarMemberDirectoryPort $members,
    private OrganizationAuthorizationPort $authorization,
    private UuidFactory $uuidFactory,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param CreateCalendarEventCommand $command the command payload
   *
   * @throws CalendarEventValidationException when the acting user has no active membership, or `endsAt` is before `startsAt`
   *
   * @return CreateCalendarEventResult the use case result
   */
  public function __invoke(CreateCalendarEventCommand $command): CreateCalendarEventResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      self::WRITE_PERMISSION,
    ]);

    $memberId = $this->members->resolveActiveMemberId($command->organizationId, $command->actorUserId);
    if (null === $memberId) {
      throw new CalendarEventValidationException('The acting user has no active membership in this organization.');
    }

    /** @var CalendarEventId $id */
    $id = $this->uuidFactory->create(CalendarEventId::class);

    $event = CalendarEvent::create(
      id: $id,
      organizationId: $command->organizationId,
      title: $command->title,
      description: $command->description,
      startsAt: $command->startsAt,
      endsAt: $command->endsAt,
      allDay: $command->allDay,
      facilityId: $command->facilityId,
      createdByMemberId: $memberId,
    );

    $this->repository->save($event);

    $this->eventDispatcher->dispatch(new CalendarEventCreatedEvent(
      organizationId: $command->organizationId,
      eventId: (string) $id,
      title: $event->title(),
      startsAt: $event->startsAt(),
      actorUserId: $command->actorUserId,
    ));

    return CreateCalendarEventResult::fromDomain($event);
  }
  // #endregion
}
