<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationPermissionsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationPermissionsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
