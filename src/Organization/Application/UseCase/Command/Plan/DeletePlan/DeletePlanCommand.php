<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Plan\DeletePlan;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DeletePlanCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeletePlanCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeletePlanCommand class.
   *
   * @since 1.0.0
   *
   * @param string $planId the plan identifier
   */
  public function __construct(
    public string $planId,
  ) {
  }
  // #endregion
}
