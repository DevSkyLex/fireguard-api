<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartCheckout;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase StartCheckoutResult.
 *
 * Carries the hosted Checkout URL the client must redirect to.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartCheckoutResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartCheckoutResult class.
   *
   * @since 1.0.0
   *
   * @param string $url the hosted Checkout URL
   */
  public function __construct(
    public string $url,
  ) {
  }
  // #endregion
}
