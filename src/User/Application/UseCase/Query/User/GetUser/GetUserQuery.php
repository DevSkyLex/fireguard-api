<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetUser;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetUserQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetUserQuery class.
   *
   * @since 1.0.0
   *
   * @param string $id the user ID
   */
  public function __construct(
    public readonly string $id,
  ) {
  }
  // #endregion
}
