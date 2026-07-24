<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\ChangeOrganizationPlan;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ChangeOrganizationPlanCommand.
 *
 * Carries the target subscription plan to assign to an organization.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChangeOrganizationPlanCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ChangeOrganizationPlanCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $planId the target plan identifier
   * @param bool $acknowledgeOveruse whether the caller accepts applying a plan whose caps are below the current usage (Stripe webhook path and confirmed self-service downgrades)
   */
  public function __construct(
    public string $organizationId,
    public string $planId,
    public bool $acknowledgeOveruse = false,
  ) {
  }
  // #endregion
}
