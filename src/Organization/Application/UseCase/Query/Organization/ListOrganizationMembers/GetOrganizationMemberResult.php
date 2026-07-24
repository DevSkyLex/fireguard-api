<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationMemberResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationMemberResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationMemberResult class.
   *
   * @since 1.0.0
   *
   * @param string $id the member identifier
   * @param string $organizationId the organization identifier
   * @param string $userId the user identifier
   * @param bool $isActive whether membership is active
   * @param DateTimeImmutable $joinedAt the membership creation datetime
   * @param list<string> $roleIds the assigned role identifiers
   * @param bool $isOwner whether this member is the organization owner
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $userId,
    public bool $isActive,
    public DateTimeImmutable $joinedAt,
    public array $roleIds,
    public bool $isOwner = false,
  ) {
  }
  // #endregion
}
