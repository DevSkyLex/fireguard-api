<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\CancelSubscription;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CancelSubscriptionCommand.
 *
 * Requests the cancellation of an organization's subscription at the end of the
 * current billing period (access is retained until then).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CancelSubscriptionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CancelSubscriptionCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   */
  public function __construct(
    public string $organizationId,
  ) {
  }
  // #endregion
}
