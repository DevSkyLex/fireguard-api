<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\UpdatePlan;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase UpdatePlanResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdatePlanResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdatePlanResult class.
   *
   * @since 1.0.0
   *
   * @param string $planId the updated plan identifier
   */
  public function __construct(
    public string $planId,
  ) {
  }
  // #endregion
}
