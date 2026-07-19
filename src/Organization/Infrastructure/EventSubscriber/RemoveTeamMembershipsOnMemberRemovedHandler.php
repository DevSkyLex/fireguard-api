<?php

declare(strict_types=1);

namespace Organization\Infrastructure\EventSubscriber;

use Organization\Application\Port\Outbound\TeamRepositoryPort;
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\ValueObject\OrganizationMemberId;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber RemoveTeamMembershipsOnMemberRemovedHandler.
 *
 * Intra-module cleanup: {@see OrganizationMemberRemovedEvent} is dispatched
 * through {@see \Shared\Application\Port\Outbound\EventDispatcherPort}
 * (the Symfony core event dispatcher, keyed by the
 * `<module>.<snake_case_event_class>` name computed by
 * `SymfonyEventDispatcherAdapter`), exactly like every other Organization
 * domain event consumed by {@see \Audit\Infrastructure\EventSubscriber\AuditEventSubscriber}.
 * Reacting to it therefore requires an {@see EventSubscriberInterface}
 * implementation (autoconfigured, no explicit tag needed) rather than a
 * `messenger.message_handler`-tagged class: the latter only fires for
 * messages dispatched through a Messenger message bus
 * ({@see \Shared\Application\Port\Outbound\EventBusPort}), which is a
 * separate mechanism used elsewhere in this codebase (e.g. `User`'s
 * `UserCreatedEvent`) but not by Organization's own domain events.
 *
 * A removed organization member is soft-deactivated
 * ({@see \Organization\Domain\Model\OrganizationMember\OrganizationMember::deactivate()}),
 * so the `team_members.member_id` foreign key almost never cascades; this
 * handler is the real cleanup path preventing dangling team memberships.
 * Reactivating the member does NOT restore prior team memberships
 * (acceptable, documented in `MODULE.md`).
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RemoveTeamMembershipsOnMemberRemovedHandler implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RemoveTeamMembershipsOnMemberRemovedHandler class.
   *
   * @since 1.0.0
   *
   * @param TeamRepositoryPort $teamRepository the team repository port
   */
  public function __construct(
    private TeamRepositoryPort $teamRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Returns the event map for the subscriber.
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'organization.organization_member_removed_event' => 'onMemberRemoved',
    ];
  }

  /**
   * Method onMemberRemoved.
   *
   * Deletes every team membership row for the removed organization member.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRemovedEvent $event the domain event
   */
  public function onMemberRemoved(OrganizationMemberRemovedEvent $event): void
  {
    $this->teamRepository->deleteMembershipsForMember(OrganizationMemberId::fromString($event->memberId));
  }
  // #endregion
}
