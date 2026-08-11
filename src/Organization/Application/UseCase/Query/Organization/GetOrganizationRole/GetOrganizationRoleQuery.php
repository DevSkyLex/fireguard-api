<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationRole;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationRoleQuery.
 *
 * Resolves a single organization-scoped role definition by identifier.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationRoleQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationRoleQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $roleId the role identifier
   */
  public function __construct(
    public string $organizationId,
    public string $roleId,
  ) {
  }
  // #endregion
}
