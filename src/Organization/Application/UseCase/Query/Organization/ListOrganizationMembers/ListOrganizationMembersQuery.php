<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationMembersQuery.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationMembersQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationMembersQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param Pagination $pagination the pagination settings
   * @param ?string $search free-text filter matched at SQL level against the
   *                        member's user identifier — the display name,
   *                        first/last name and email live in the User
   *                        module (auth database) and cannot be joined here
   * @param ?string $status the membership status filter: `active`,
   *                        `inactive`, or `all`/`null` (no filter)
   * @param ?string $roleId filters members holding this organization role
   * @param Sorting $sorting the sort field (`joinedAt` or `displayName`,
   *                         the latter falling back to the member's user
   *                         identifier for the same reason as `$search`)
   *                         and direction
   */
  public function __construct(
    public string $organizationId,
    public Pagination $pagination = new Pagination(),
    public ?string $search = null,
    public ?string $status = null,
    public ?string $roleId = null,
    public Sorting $sorting = new Sorting('joinedAt', SortDirection::ASC),
  ) {
  }
  // #endregion
}
