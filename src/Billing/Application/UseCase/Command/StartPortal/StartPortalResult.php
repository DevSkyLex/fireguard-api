<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartPortal;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase StartPortalResult.
 *
 * Carries the hosted Billing Portal URL the client must redirect to.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartPortalResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartPortalResult class.
   *
   * @since 1.0.0
   *
   * @param string $url the hosted Billing Portal URL
   */
  public function __construct(
    public string $url,
  ) {
  }
  // #endregion
}
