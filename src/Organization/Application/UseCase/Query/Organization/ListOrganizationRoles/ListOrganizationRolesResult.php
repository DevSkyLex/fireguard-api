<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationRolesResult.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationRolesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListOrganizationRolesResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetOrganizationRoleResult> $roles the organization roles —
   *                                               the current page when the
   *                                               query requested pagination,
   *                                               or every role otherwise
   * @param int $total the total number of roles in the organization,
   *                   regardless of pagination
   */
  public function __construct(
    public array $roles,
    public int $total = 0,
  ) {
  }
  // #endregion
}
