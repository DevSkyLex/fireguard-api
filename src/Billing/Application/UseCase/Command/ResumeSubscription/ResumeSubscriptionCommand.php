<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\ResumeSubscription;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ResumeSubscriptionCommand.
 *
 * Requests clearing a scheduled cancellation so the organization's subscription
 * renews normally.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResumeSubscriptionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResumeSubscriptionCommand class.
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
