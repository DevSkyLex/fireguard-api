<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationRolesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationRolesQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
