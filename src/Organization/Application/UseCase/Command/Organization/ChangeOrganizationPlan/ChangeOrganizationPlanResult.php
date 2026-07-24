<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ChangeOrganizationPlanResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeOrganizationPlanResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ChangeOrganizationPlanResult class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the updated organization identifier
   * @param string $planId the assigned plan identifier
   */
  public function __construct(
    public string $organizationId,
    public string $planId,
  ) {
  }
  // #endregion
}
