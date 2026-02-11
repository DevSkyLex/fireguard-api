<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationMembersResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationMembersResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListOrganizationMembersResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetOrganizationMemberResult> $members the organization members
   */
  public function __construct(
    public array $members,
  ) {
  }
  // #endregion
}
