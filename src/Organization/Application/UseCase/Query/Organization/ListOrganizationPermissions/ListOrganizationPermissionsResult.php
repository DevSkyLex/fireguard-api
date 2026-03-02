<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListOrganizationPermissionsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationPermissionsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListOrganizationPermissionsResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetOrganizationPermissionResult> $permissions the organization permissions
   */
  public function __construct(
    public array $permissions,
  ) {
  }
  // #endregion
}
