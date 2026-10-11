<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Delivery;

/**
 * Class PendingWebhookDelivery
 *
 * Identity of a durable delivery generation eligible for recovery.
 *
 * @category Contract
 */
final readonly class PendingWebhookDelivery
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $id delivery identifier
   * @param int $generation exact generation eligible for recovery
   *
   * @return void
   */
  public function __construct(public string $id, public int $generation)
  {
  }
  // #endregion
}
