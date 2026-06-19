<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\CreatePlan;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreatePlanResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreatePlanResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreatePlanResult class.
   *
   * @since 1.0.0
   *
   * @param string $planId the created plan identifier
   */
  public function __construct(
    public string $planId,
  ) {
  }
  // #endregion
}
