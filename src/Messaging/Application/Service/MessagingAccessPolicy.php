<?php

declare(strict_types=1);

namespace Messaging\Application\Service;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

/**
 * Service MessagingAccessPolicy.
 *
 * Centralizes every Messaging use case's authorization gate:
 * `organization.messaging.{read,write,manage}` for both v1 subject threads
 * and v2 channels, PLUS the subject's own required permission (subject
 * threads) or `messaging_participants` membership (channels). Fail-closed:
 * every assertion throws on the first missing permission.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingAccessPolicy
{
  // #region Constants
  private const string READ = 'organization.messaging.read';

  private const string WRITE = 'organization.messaging.write';

  private const string MANAGE = 'organization.messaging.manage';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param MessagingMemberDirectoryPort $members the messaging member directory port
   * @param MessagingParticipantRepositoryPort $participants the messaging participant repository port
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private MessagingMemberDirectoryPort $members,
    private MessagingParticipantRepositoryPort $participants,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method assertCanReadThread.
   *
   * Asserts the user holds both `organization.messaging.read` and the
   * subject's own read permission. Subject-thread (v1) conversations only.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $requiredSubjectPermission the subject's own required read permission
   */
  public function assertCanReadThread(string $userId, string $organizationId, string $requiredSubjectPermission): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [$requiredSubjectPermission, self::READ]);
  }

  /**
   * Method assertCanListConversations.
   *
   * Gates `ListConversations` by `organization.messaging.read` alone (no
   * per-subject filtering, for cost); opening a specific thread additionally
   * requires the subject's own read permission via
   * {@see self::assertCanReadThread()}.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   */
  public function assertCanListConversations(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::READ]);
  }

  /**
   * Method assertCanWrite.
   *
   * Asserts the user holds both `organization.messaging.write` and the
   * subject's own read permission. Subject-thread (v1) conversations only.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $requiredSubjectPermission the subject's own required read permission
   */
  public function assertCanWrite(string $userId, string $organizationId, string $requiredSubjectPermission): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [$requiredSubjectPermission, self::WRITE]);
  }

  /**
   * Method assertCanUseMessaging.
   *
   * Asserts the user holds `organization.messaging.read` — the floor
   * permission required to use ANY part of the messaging module. Used by
   * {@see \Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\GetOrCreateDirectConversationHandler}
   * (L2.4) to start a direct conversation: unlike a subject thread, there is
   * no resolver-backed subject to layer an extra permission on top of (see
   * {@see self::assertCanReadThread()}), so the messaging-wide read
   * permission is the only gate.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   */
  public function assertCanUseMessaging(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::READ]);
  }

  /**
   * Method assertCanManage.
   *
   * Asserts the user holds `organization.messaging.manage` (archival and
   * cross-member moderation).
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   */
  public function assertCanManage(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::MANAGE]);
  }

  /**
   * Method hasManagePermission.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return bool true when the user holds `organization.messaging.manage`
   */
  public function hasManagePermission(string $userId, string $organizationId): bool
  {
    return $this->authorization->hasPermission($userId, $organizationId, self::MANAGE);
  }

  /**
   * Method hasReadPermission.
   *
   * Non-throwing twin of the `organization.messaging.read` half of
   * {@see self::assertCanReadThread()}/{@see self::assertCanListConversations()} —
   * for bulk/list-shaped call sites (e.g. the unified inbox mention source)
   * that must check many conversations in one authorization pass instead of
   * throwing per row.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return bool true when the user holds `organization.messaging.read`
   */
  public function hasReadPermission(string $userId, string $organizationId): bool
  {
    return $this->authorization->hasPermission($userId, $organizationId, self::READ);
  }

  /**
   * Method hasPermission.
   *
   * Non-throwing check for an arbitrary organization permission (e.g. a
   * subject's own `organization.facilities.read`). Used by bulk/list-shaped
   * call sites that must check several DIFFERENT subject permissions in one
   * pass (e.g. the unified inbox mention source, which cannot afford
   * `MessagingSubjectResolverRegistry::resolve()`'s per-subject existence
   * query for every candidate row) without duplicating a second
   * authorization access path outside this policy.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $permission the permission name
   *
   * @return bool true when the user holds the given permission
   */
  public function hasPermission(string $userId, string $organizationId, string $permission): bool
  {
    return $this->authorization->hasPermission($userId, $organizationId, $permission);
  }

  /**
   * Method assertCanReadChannel.
   *
   * Asserts the user holds `organization.messaging.read` AND is either a
   * channel participant OR holds `organization.messaging.manage` (managers
   * can read any channel for moderation, mirroring subject-thread
   * behavior). Channel (v2) conversations only.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the acting member identifier
   */
  public function assertCanReadChannel(string $userId, string $organizationId, string $conversationId, string $memberId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::READ]);

    if ($this->hasManagePermission($userId, $organizationId)) {
      return;
    }

    if (!$this->participants->isParticipant($conversationId, $memberId)) {
      throw new MessagingAccessDeniedException('The current user is not a participant of this channel.');
    }
  }

  /**
   * Method assertCanWriteChannel.
   *
   * Asserts the user holds `organization.messaging.write` AND is a channel
   * participant (unlike reading, `.manage` does NOT bypass participation
   * for writing). Channel (v2) conversations only.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the acting member identifier
   */
  public function assertCanWriteChannel(string $userId, string $organizationId, string $conversationId, string $memberId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::WRITE]);

    if (!$this->participants->isParticipant($conversationId, $memberId)) {
      throw new MessagingAccessDeniedException('The current user is not a participant of this channel.');
    }
  }

  /**
   * Method assertCanManageChannels.
   *
   * Asserts the user holds `organization.messaging.manage` — required to
   * create a channel, rename/archive it, manage its participants, or
   * bind/unbind its team.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   */
  public function assertCanManageChannels(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions($userId, $organizationId, [self::MANAGE]);
  }

  /**
   * Method resolveActiveMemberId.
   *
   * Resolves the authenticated user's active organization member id, so
   * every Messaging write attributes its actor to a member IRI.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   *
   * @return string the resolved active member identifier
   */
  public function resolveActiveMemberId(string $organizationId, string $userId): string
  {
    $memberId = $this->members->resolveActiveMemberId($organizationId, $userId);
    if (null === $memberId) {
      throw MessagingNotFoundException::outsideScope();
    }

    return $memberId;
  }
  // #endregion
}
