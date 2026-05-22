<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListOrganizationInvitationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListOrganizationInvitationsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationInvitationsQuery class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization ID
   * @param Pagination $pagination the pagination settings
   */
  public function __construct(
    public string $organizationId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
