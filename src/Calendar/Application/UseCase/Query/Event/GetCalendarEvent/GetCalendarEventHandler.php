<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\Event\GetCalendarEvent;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Domain\ValueObject\CalendarEventId;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetCalendarEventHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarEventHandler implements QueryHandler
{
  // #region Constants
  private const string READ_PERMISSION = 'organization.events.read';
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
   * @param GetCalendarEventQuery $query the query payload
   *
   * @throws CalendarEventNotFoundException when the event does not exist in this organization
   *
   * @return GetCalendarEventResult the query result
   */
  public function __invoke(GetCalendarEventQuery $query): GetCalendarEventResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      self::READ_PERMISSION,
    ]);

    $id = CalendarEventId::fromString($query->eventId);
    $event = $this->repository->findById($id);

    if (null === $event || $event->organizationId() !== $query->organizationId) {
      throw CalendarEventNotFoundException::withId($query->eventId);
    }

    return GetCalendarEventResult::fromDomain($event);
  }
  // #endregion
}
