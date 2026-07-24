<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\EventSubscriber;

use Messaging\Application\Service\MessagingChannelParticipantSynchronizer;
use Organization\Domain\Event\Member\OrganizationMemberRemovedEvent;
use Organization\Domain\Event\Team\{TeamDeletedEvent, TeamMemberAddedEvent, TeamMemberRemovedEvent};
use Shared\Application\Port\Outbound\LoggerPort;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

/**
 * Subscriber SyncTeamChannelParticipantsSubscriber.
 *
 * Reconciles team-bound channels' participants by reacting to Organization
 * domain events BY NAME, exactly like
 * `Organization\Infrastructure\EventSubscriber\RemoveTeamMembershipsOnMemberRemovedHandler`:
 * these events are dispatched through
 * `Shared\Application\Port\Outbound\EventDispatcherPort` (the Symfony core
 * event dispatcher, keyed by the `<module>.<snake_case_event_class>` name
 * computed by `SymfonyEventDispatcherAdapter`), so reacting to them requires
 * an `EventSubscriberInterface` implementation (autoconfigured, no explicit
 * tag) rather than a `messenger.message_handler`-tagged class.
 *
 * Kept THIN — every method delegates to
 * {@see MessagingChannelParticipantSynchronizer} and swallows errors (never
 * lets a reconciliation failure break the Organization-side action that
 * triggered it), mirroring `AuditEventSubscriber::dispatchAuditEvent()`.
 *
 * Subscribes to `organization.organization_member_removed_event` in
 * addition to the team events because the org-member-removal cascade
 * ({@see \Organization\Infrastructure\EventSubscriber\RemoveTeamMembershipsOnMemberRemovedHandler})
 * deletes team memberships WITHOUT dispatching a per-team
 * `TeamMemberRemovedEvent` — this is the only path that purges a removed
 * member from every channel (team-bound or manual) they participated in.
 *
 * @category Subscriber
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SyncTeamChannelParticipantsSubscriber implements EventSubscriberInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingChannelParticipantSynchronizer $synchronizer the channel participant synchronizer
   * @param LoggerPort $logger the logger value
   */
  public function __construct(
    private MessagingChannelParticipantSynchronizer $synchronizer,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [
      'organization.team_member_added_event' => 'onTeamMemberAdded',
      'organization.team_member_removed_event' => 'onTeamMemberRemoved',
      'organization.team_deleted_event' => 'onTeamDeleted',
      'organization.organization_member_removed_event' => 'onOrganizationMemberRemoved',
    ];
  }

  /**
   * Method onTeamMemberAdded.
   *
   * @since 1.0.0
   *
   * @param TeamMemberAddedEvent $event the domain event
   */
  public function onTeamMemberAdded(TeamMemberAddedEvent $event): void
  {
    $this->run(
      fn () => $this->synchronizer->onTeamMemberAdded($event->organizationId, $event->teamId, $event->memberId),
      $event,
    );
  }

  /**
   * Method onTeamMemberRemoved.
   *
   * @since 1.0.0
   *
   * @param TeamMemberRemovedEvent $event the domain event
   */
  public function onTeamMemberRemoved(TeamMemberRemovedEvent $event): void
  {
    $this->run(
      fn () => $this->synchronizer->onTeamMemberRemoved($event->organizationId, $event->teamId, $event->memberId),
      $event,
    );
  }

  /**
   * Method onTeamDeleted.
   *
   * @since 1.0.0
   *
   * @param TeamDeletedEvent $event the domain event
   */
  public function onTeamDeleted(TeamDeletedEvent $event): void
  {
    $this->run(
      fn () => $this->synchronizer->onTeamDeleted($event->organizationId, $event->teamId),
      $event,
    );
  }

  /**
   * Method onOrganizationMemberRemoved.
   *
   * @since 1.0.0
   *
   * @param OrganizationMemberRemovedEvent $event the domain event
   */
  public function onOrganizationMemberRemoved(OrganizationMemberRemovedEvent $event): void
  {
    $this->run(
      fn () => $this->synchronizer->onOrganizationMemberRemoved($event->organizationId, $event->memberId),
      $event,
    );
  }

  /**
   * Method run.
   *
   * Runs a synchronizer call, swallowing and logging any failure so a
   * channel-reconciliation error never breaks the Organization-side action
   * that triggered it.
   *
   * @since 1.0.0
   *
   * @param callable(): void $callback the synchronizer call to run
   * @param object $event the triggering domain event, for logging context
   */
  private function run(callable $callback, object $event): void
  {
    try {
      $callback();
    } catch (Throwable $exception) {
      $this->logger->warning('Messaging channel participant reconciliation failed.', [
        'event' => $event::class,
        'error' => $exception->getMessage(),
      ]);
    }
  }
  // #endregion
}
