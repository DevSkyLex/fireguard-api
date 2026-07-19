<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\ValueObject\CalendarEventId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase UpdateCalendarEventHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateCalendarEventHandler implements CommandHandler
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
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private CalendarEventRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param UpdateCalendarEventCommand $command the command payload
   *
   * @throws CalendarEventNotFoundException when the event does not exist in this organization
   *
   * @return UpdateCalendarEventResult the use case result
   */
  public function __invoke(UpdateCalendarEventCommand $command): UpdateCalendarEventResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      self::WRITE_PERMISSION,
    ]);

    $id = CalendarEventId::fromString($command->eventId);
    $event = $this->repository->findById($id);

    if (null === $event || $event->organizationId() !== $command->organizationId) {
      throw CalendarEventNotFoundException::withId($command->eventId);
    }

    $event->update(
      title: $command->title ?? $event->title(),
      description: $command->description ?? $event->description(),
      startsAt: $command->startsAt ?? $event->startsAt(),
      endsAt: $command->endsAt ?? $event->endsAt(),
      allDay: $command->allDay ?? $event->allDay(),
      facilityId: $command->facilityId ?? $event->facilityId(),
    );

    $this->repository->save($event);

    return UpdateCalendarEventResult::fromDomain($event);
  }
  // #endregion
}
