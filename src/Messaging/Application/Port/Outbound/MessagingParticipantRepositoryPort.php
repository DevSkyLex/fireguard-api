<?php

declare(strict_types=1);

namespace Messaging\Application\Port\Outbound;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ParticipantView;

/**
 * Port MessagingParticipantRepositoryPort.
 *
 * Manages `messaging_participants` rows: the explicit membership list that
 * gates a `visibility=PARTICIPANTS` channel (see
 * {@see \Messaging\Application\Service\MessagingAccessPolicy}). A row's
 * `source` (`manual` or `team`) makes team reconciliation deterministic —
 * only `team`-sourced rows are ever pruned by the event-driven sync.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MessagingParticipantRepositoryPort
{
  // #region Methods
  /**
   * Method addParticipant.
   *
   * Adds a participant. Implementations MUST be idempotent (a raw DBAL
   * insert with the unique-constraint violation swallowed, or
   * `ON CONFLICT DO NOTHING`) — never ORM `persist()`/`flush()`, which
   * would close the EntityManager on the conflict (mirrors
   * `MessagingConversationRepository::getOrCreate()`).
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the participant's organization member identifier
   * @param ?string $role the free-form membership label, if any
   * @param string $source `manual` or `team`
   * @param DateTimeImmutable $addedAt the date the participant joined
   */
  public function addParticipant(
    string $conversationId,
    string $organizationId,
    string $memberId,
    ?string $role,
    string $source,
    DateTimeImmutable $addedAt,
  ): void;

  /**
   * Method removeParticipant.
   *
   * Removes a participant. Idempotent (a no-op when not a participant).
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the participant's organization member identifier
   */
  public function removeParticipant(string $conversationId, string $memberId): void;

  /**
   * Method isParticipant.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the organization member identifier
   *
   * @return bool true when the member is a participant of the channel
   */
  public function isParticipant(string $conversationId, string $memberId): bool;

  /**
   * Method listParticipants.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   *
   * @return list<ParticipantView> the channel's participants, oldest first
   */
  public function listParticipants(string $conversationId): array;

  /**
   * Method listMemberIds.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   *
   * @return list<string> the participant member identifiers
   */
  public function listMemberIds(string $conversationId): array;

  /**
   * Method listChannelIdsForMember.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the organization member identifier
   *
   * @return list<string> the channel (conversation) identifiers the member participates in
   */
  public function listChannelIdsForMember(string $organizationId, string $memberId): array;

  /**
   * Method replaceParticipants.
   *
   * Full team-resync: reconciles the `team`-sourced participants of a
   * channel with the given member list (adds the missing ones with
   * `source=team`, removes the `team`-sourced rows no longer present).
   * `manual`-sourced rows are NEVER touched by this method.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $organizationId the owning organization identifier
   * @param list<string> $memberIds the team's current active member identifiers
   */
  public function replaceParticipants(string $conversationId, string $organizationId, array $memberIds): void;

  /**
   * Method removeMemberFromAllChannels.
   *
   * Purges a member from EVERY channel of the organization, regardless of
   * `source` — used when the member is removed from the organization
   * itself (`organization.organization_member_removed_event`), not merely
   * from a team.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the organization member identifier
   */
  public function removeMemberFromAllChannels(string $organizationId, string $memberId): void;

  /**
   * Method addMemberToChannels.
   *
   * Bulk-adds a member (idempotent per channel) to the given channels with
   * the given `source`.
   *
   * @since 1.0.0
   *
   * @param list<string> $conversationIds the channel (conversation) identifiers
   * @param string $organizationId the owning organization identifier
   * @param string $memberId the organization member identifier
   * @param string $source `manual` or `team`
   */
  public function addMemberToChannels(array $conversationIds, string $organizationId, string $memberId, string $source): void;

  /**
   * Method removeMemberFromChannels.
   *
   * Bulk-removes a member from the given channels, but ONLY the
   * `team`-sourced row: a member independently added as a `manual`
   * participant of a team-bound channel keeps their access when they leave
   * the team.
   *
   * @since 1.0.0
   *
   * @param list<string> $conversationIds the channel (conversation) identifiers
   * @param string $memberId the organization member identifier
   */
  public function removeMemberFromChannels(array $conversationIds, string $memberId): void;
  // #endregion
}
