<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Plan\ListPlans;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListPlansQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListPlansQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListPlansQuery class.
   *
   * @since 1.0.0
   *
   * @param bool $activeOnly whether to restrict the result to selectable plans
   */
  public function __construct(
    public bool $activeOnly = false,
  ) {
  }
  // #endregion
}
