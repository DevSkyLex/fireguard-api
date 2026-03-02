<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationPermissionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationPermissionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationPermissionResult class.
   *
   * @since 1.0.0
   *
   * @param string $name the permission name
   * @param string $description the permission description
   */
  public function __construct(
    public string $name,
    public string $description,
  ) {
  }
  // #endregion
}
