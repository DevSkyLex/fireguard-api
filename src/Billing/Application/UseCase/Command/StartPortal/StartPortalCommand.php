<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Command\StartPortal;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase StartPortalCommand.
 *
 * Requests a hosted Stripe Billing Portal session for an organization so it can
 * manage, change or cancel its subscription and payment method.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartPortalCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StartPortalCommand class.
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
