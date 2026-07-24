<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AddOrganizationMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddOrganizationMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddOrganizationMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AddOrganizationMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   * @param list<string> $roleIds the role identifiers to assign
   * @param bool $sendMemberNotification whether to notify the member when access is granted by admin flow
   * @param bool $enforceQuota whether to enforce the member cap in-transaction (false on the accept path, which already counts active-only slots)
   * @param bool $emitMemberAddedEvent whether this handler dispatches the member-added audit event itself (false when the caller runs it inside an outer transaction — e.g. the accept path — and dispatches post-commit)
   */
  public function __construct(
    public string $organizationId,
    public string $userId,
    public array $roleIds = [],
    public bool $sendMemberNotification = true,
    public bool $enforceQuota = true,
    public bool $emitMemberAddedEvent = true,
  ) {
  }
  // #endregion
}
