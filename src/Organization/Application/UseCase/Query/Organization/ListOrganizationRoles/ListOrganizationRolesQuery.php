<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationRoles;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationRolesQuery.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationRolesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationRolesQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param ?Pagination $pagination the pagination settings, or null to
   *                                return every role unpaginated — the
   *                                default, deliberately unlike
   *                                {@see \Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\ListOrganizationMembersQuery},
   *                                because two existing callers
   *                                (ListOrganizationRolesProvider's and
   *                                ListOrganizationMembersProvider's role
   *                                name lookups) need the full role list and
   *                                would otherwise silently truncate to the
   *                                first page
   */
  public function __construct(
    public string $organizationId,
    public ?Pagination $pagination = null,
  ) {
  }
  // #endregion
}
