<?php

declare(strict_types=1);

namespace Authorization\Application\UseCase\Query\Role\ListRoles;

use Shared\Application\Message\QueryMessage;

/**
 * Query ListRolesQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListRolesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool|null $isSystem optional filter to return only system or non-system roles
   */
  public function __construct(
    public readonly ?bool $isSystem = null,
  ) {
  }
  // #endregion
}
