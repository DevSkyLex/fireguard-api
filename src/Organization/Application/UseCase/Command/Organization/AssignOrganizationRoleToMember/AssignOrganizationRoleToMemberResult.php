<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\AssignOrganizationRoleToMember;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AssignOrganizationRoleToMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssignOrganizationRoleToMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the AssignOrganizationRoleToMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the organization member identifier
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier that was assigned
   * @param list<string> $roleIds the current role identifiers assigned to the member
   * @param string $userId the member user identifier
   * @param bool $isActive whether the membership is active
   * @param DateTimeImmutable $joinedAt the membership join datetime
   */
  public function __construct(
    public string $memberId,
    public string $organizationId,
    public string $roleId,
    public array $roleIds,
    public string $userId,
    public bool $isActive,
    public DateTimeImmutable $joinedAt,
  ) {
  }
  // #endregion
}
