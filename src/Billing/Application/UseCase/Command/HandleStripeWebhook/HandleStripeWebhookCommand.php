<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\HandleStripeWebhook;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase HandleStripeWebhookCommand.
 *
 * Carries the raw Stripe webhook request so the handler can verify its signature
 * and reconcile the local subscription state.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class HandleStripeWebhookCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the HandleStripeWebhookCommand class.
   *
   * @since 1.0.0
   *
   * @param string $payload the raw request body
   * @param string $signature the value of the Stripe-Signature header
   */
  public function __construct(
    public string $payload,
    public string $signature,
  ) {
  }
  // #endregion
}
