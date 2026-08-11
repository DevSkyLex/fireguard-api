<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\SetOrganizationMemberRoles;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase SetOrganizationMemberRolesResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetOrganizationMemberRolesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the SetOrganizationMemberRolesResult class.
   *
   * @since 1.0.0
   *
   * @param string $memberId the organization member identifier
   * @param string $organizationId the organization identifier
   * @param string $userId the member user identifier
   * @param list<string> $roleIds the member's final role identifiers
   * @param bool $isActive whether the membership is active
   * @param DateTimeImmutable $joinedAt the membership join datetime
   */
  public function __construct(
    public string $memberId,
    public string $organizationId,
    public string $userId,
    public array $roleIds,
    public bool $isActive,
    public DateTimeImmutable $joinedAt,
  ) {
  }
  // #endregion
}
