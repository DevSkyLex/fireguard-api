<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCurrentOrganizationMemberProfileResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentOrganizationMemberProfileResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetCurrentOrganizationMemberProfileResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the member identifier
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   * @param bool $isActive whether the membership is active
   * @param DateTimeImmutable $joinedAt the member joined-at datetime
   * @param list<GetCurrentOrganizationMemberProfileRoleResult> $roles the assigned roles
   * @param list<string> $permissions the effective permission names
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $userId,
    public bool $isActive,
    public DateTimeImmutable $joinedAt,
    public array $roles,
    public array $permissions,
  ) {
  }
  // #endregion
}
