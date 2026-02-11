<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase InviteOrganizationMemberCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InviteOrganizationMemberCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InviteOrganizationMemberCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $email the invited email
   * @param string $invitedByUserId the inviter user identifier
   * @param list<string> $roleIds requested role identifiers
   */
  public function __construct(
    public string $organizationId,
    public string $email,
    public string $invitedByUserId,
    public array $roleIds = [],
  ) {
  }
  // #endregion
}
