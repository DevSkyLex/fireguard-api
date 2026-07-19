<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Event\CalendarEventDeletedEvent;
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\ValueObject\CalendarEventId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * UseCase DeleteCalendarEventHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCalendarEventHandler implements CommandHandler
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
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   */
  public function __construct(
    private CalendarEventRepositoryPort $repository,
    private OrganizationAuthorizationPort $authorization,
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
   * @param DeleteCalendarEventCommand $command the command payload
   *
   * @throws CalendarEventNotFoundException when the event does not exist in this organization
   *
   * @return DeleteCalendarEventResult the use case result
   */
  public function __invoke(DeleteCalendarEventCommand $command): DeleteCalendarEventResult
  {
    $this->authorization->assertGrantedPermissions($command->actorUserId, $command->organizationId, [
      self::WRITE_PERMISSION,
    ]);

    $id = CalendarEventId::fromString($command->eventId);
    $event = $this->repository->findById($id);

    if (null === $event || $event->organizationId() !== $command->organizationId) {
      throw CalendarEventNotFoundException::withId($command->eventId);
    }

    $this->repository->remove($event);

    $this->eventDispatcher->dispatch(new CalendarEventDeletedEvent(
      organizationId: $command->organizationId,
      eventId: $command->eventId,
      actorUserId: $command->actorUserId,
    ));

    return new DeleteCalendarEventResult(
      eventId: $command->eventId,
      organizationId: $command->organizationId,
    );
  }
  // #endregion
}
