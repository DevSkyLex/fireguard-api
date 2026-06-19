<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\DeletePlan;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeletePlanResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeletePlanResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeletePlanResult class.
   *
   * @since 1.0.0
   *
   * @param string $planId the deleted plan identifier
   */
  public function __construct(
    public string $planId,
  ) {
  }
  // #endregion
}
