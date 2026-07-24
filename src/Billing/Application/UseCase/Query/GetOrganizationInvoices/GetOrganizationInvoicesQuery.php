<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationInvoices;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetOrganizationInvoicesQuery.
 *
 * Reads the recent billing invoices of an organization from Stripe.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvoicesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationInvoicesQuery class.
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
