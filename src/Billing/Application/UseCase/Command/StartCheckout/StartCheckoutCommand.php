<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartCheckout;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase StartCheckoutCommand.
 *
 * Requests a hosted Stripe Checkout session to subscribe an organization to a
 * paid plan for the chosen billing cadence.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartCheckoutCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartCheckoutCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $planKey the target plan key (e.g. pro, max)
   * @param string $interval the billing cadence (month or year)
   */
  public function __construct(
    public string $organizationId,
    public string $planKey,
    public string $interval,
  ) {
  }
  // #endregion
}
