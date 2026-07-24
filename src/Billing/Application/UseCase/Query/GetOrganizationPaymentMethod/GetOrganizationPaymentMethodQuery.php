<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationPaymentMethod;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationPaymentMethodQuery.
 *
 * Reads the organization's saved Stripe payment method (brand, last 4 digits,
 * expiry).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationPaymentMethodQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationPaymentMethodQuery class.
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
