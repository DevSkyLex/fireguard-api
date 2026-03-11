<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListUserOrganizationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListUserOrganizationsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $userId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
