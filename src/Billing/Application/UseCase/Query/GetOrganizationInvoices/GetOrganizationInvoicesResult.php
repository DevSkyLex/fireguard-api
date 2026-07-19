<?php

declare(strict_types=1);

namespace Billing\Application\UseCase\Query\GetOrganizationInvoices;

use Billing\Application\Contract\Stripe\StripeInvoice;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationInvoicesResult.
 *
 * Carries the organization's recent invoices, newest first. The list is empty
 * when the organization has no Stripe customer yet.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvoicesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationInvoicesResult class.
   *
   * @since 1.0.0
   *
   * @param list<StripeInvoice> $invoices the recent invoices
   */
  public function __construct(
    public array $invoices,
  ) {
  }
  // #endregion
}
