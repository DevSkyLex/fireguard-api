<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use DateTimeImmutable;
use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Organization\Application\Port\Inbound\TeamDirectoryPort;

use function array_diff;
use function array_filter;
use function array_values;

/**
 * Service MessagingChannelParticipantSynchronizer.
 *
 * Reconciles a team-bound channel's participant set from
 * `TeamDirectoryPort`. Two entry points:
 *
 * - {@see self::resyncTeamBinding()}: a full resync, run once when a channel
 *   is bound to a team (`BindChannelTeamHandler`) — covers the initial
 *   population and any event missed while unbound.
 * - `onTeamMemberAdded()`/`onTeamMemberRemoved()`/`onTeamDeleted()`/
 *   `onOrganizationMemberRemoved()`: incremental, event-driven reconciliation
 *   consumed by {@see \Messaging\Infrastructure\EventSubscriber\SyncTeamChannelParticipantsSubscriber}
 *   (kept thin, delegating here, mirroring `AuditEventSubscriber`).
 *
 * A newly added participant's read marker is seeded at the join instant so
 * they are never flooded with pre-join unread messages; an already-present
 * participant's marker is NEVER touched by a resync. Team-sync participant
 * changes are deliberately NOT individually audited (the team event itself
 * is already audited, and per-member audit rows would be high-volume) —
 * only the manual API paths (`AddChannelParticipantHandler`/
 * `RemoveChannelParticipantHandler`) dispatch audited domain events.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelParticipantSynchronizer
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingParticipantRepositoryPort $participants the participant repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param TeamDirectoryPort $teamDirectory the organization team directory port
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private TeamDirectoryPort $teamDirectory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method resyncTeamBinding.
   *
   * Full resync: reconciles the channel's `team`-sourced participants with
   * the team's CURRENT active members. Only the members newly added by
   * this resync get their read marker seeded — an already-present
   * participant keeps their existing unread position.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $conversationId the channel (conversation) identifier
   * @param string $teamId the organization team identifier
   */
  public function resyncTeamBinding(string $organizationId, string $conversationId, string $teamId): void
  {
    $existingMemberIds = $this->participants->listMemberIds($conversationId);
    $activeMemberIds = $this->teamDirectory->listActiveMemberIds($organizationId, $teamId);

    $this->participants->replaceParticipants($conversationId, $organizationId, $activeMemberIds);

    $newlyAdded = array_values(array_diff($activeMemberIds, $existingMemberIds));
    $this->seedReadMarkers($organizationId, [$conversationId], $newlyAdded);
  }

  /**
   * Method onTeamMemberAdded.
   *
   * Adds the member to every channel currently bound to the team
   * (idempotent per channel), seeding a read marker only for the channels
   * where the member was NOT already a participant.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $teamId the organization team identifier
   * @param string $memberId the added member's identifier
   */
  public function onTeamMemberAdded(string $organizationId, string $teamId, string $memberId): void
  {
    $channelIds = $this->conversations->findChannelIdsBoundToTeam($organizationId, $teamId);
    if ([] === $channelIds) {
      return;
    }

    $newChannelIds = array_values(array_filter(
      $channelIds,
      fn (string $channelId): bool => !$this->participants->isParticipant($channelId, $memberId),
    ));

    if ([] === $newChannelIds) {
      return;
    }

    $this->participants->addMemberToChannels($newChannelIds, $organizationId, $memberId, 'team');
    $this->seedReadMarkersForMember($organizationId, $newChannelIds, $memberId);
  }

  /**
   * Method onTeamMemberRemoved.
   *
   * Removes the member from every channel bound to the team, but ONLY the
   * `team`-sourced row — a member independently added as a `manual`
   * participant keeps their access.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $teamId the organization team identifier
   * @param string $memberId the removed member's identifier
   */
  public function onTeamMemberRemoved(string $organizationId, string $teamId, string $memberId): void
  {
    $channelIds = $this->conversations->findChannelIdsBoundToTeam($organizationId, $teamId);
    if ([] === $channelIds) {
      return;
    }

    $this->participants->removeMemberFromChannels($channelIds, $memberId);
  }

  /**
   * Method onTeamDeleted.
   *
   * Unbinds every channel bound to the deleted team. The current
   * participant snapshot is RETAINED (documented product decision) — this
   * only clears the binding, never removes a participant.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $teamId the deleted organization team identifier
   */
  public function onTeamDeleted(string $organizationId, string $teamId): void
  {
    $channelIds = $this->conversations->findChannelIdsBoundToTeam($organizationId, $teamId);

    foreach ($channelIds as $channelId) {
      $conversation = $this->conversations->findAggregateById($channelId);
      if (null === $conversation) {
        continue;
      }

      $conversation->unbindTeam();
      $this->conversations->save($conversation);
    }
  }

  /**
   * Method onOrganizationMemberRemoved.
   *
   * Purges the removed organization member from EVERY channel, regardless
   * of `source` — required because the org-member-removal cascade
   * ({@see \Organization\Infrastructure\EventSubscriber\RemoveTeamMembershipsOnMemberRemovedHandler})
   * deletes team memberships WITHOUT dispatching a per-team
   * `TeamMemberRemovedEvent`, so `onTeamMemberRemoved()` never fires for it.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the removed member's identifier
   */
  public function onOrganizationMemberRemoved(string $organizationId, string $memberId): void
  {
    $this->participants->removeMemberFromAllChannels($organizationId, $memberId);
  }

  /**
   * Method seedReadMarkers.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param list<string> $conversationIds the channel (conversation) identifiers
   * @param list<string> $memberIds the newly added member identifiers
   */
  private function seedReadMarkers(string $organizationId, array $conversationIds, array $memberIds): void
  {
    $now = new DateTimeImmutable();
    foreach ($conversationIds as $conversationId) {
      foreach ($memberIds as $memberId) {
        $this->readMarkers->upsert($conversationId, $organizationId, $memberId, $now, null);
      }
    }
  }

  /**
   * Method seedReadMarkersForMember.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param list<string> $conversationIds the channel (conversation) identifiers newly joined
   * @param string $memberId the newly added member's identifier
   */
  private function seedReadMarkersForMember(string $organizationId, array $conversationIds, string $memberId): void
  {
    $this->seedReadMarkers($organizationId, $conversationIds, [$memberId]);
  }
  // #endregion
}
