<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeSelfServiceOrganizationPlan;

use Shared\Application\Message\CommandMessage;

/**
 * Command ChangeSelfServiceOrganizationPlanCommand.
 *
 * Carries an authenticated actor for the free-plan self-service capability.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeSelfServiceOrganizationPlanCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $planId the requested free plan identifier
   * @param string $actorUserId the authenticated account identifier
   * @param bool $acknowledgeOveruse whether current overage is acknowledged
   */
  public function __construct(public string $organizationId, public string $planId, public string $actorUserId, public bool $acknowledgeOveruse = false)
  {
  }
  // #endregion
}
