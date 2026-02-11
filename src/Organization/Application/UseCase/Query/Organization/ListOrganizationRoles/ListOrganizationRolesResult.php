<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationRolesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
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
   * @param list<GetOrganizationRoleResult> $roles the organization roles
   */
  public function __construct(
    public array $roles,
  ) {
  }
  // #endregion
}
