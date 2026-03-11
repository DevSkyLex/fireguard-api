<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\Tenant\ListTenants;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * Query ListTenantsQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTenantsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListTenantsQuery class.
   *
   * @since 1.0.0
   */
  public function __construct(
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
