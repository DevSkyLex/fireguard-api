<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Operation;

/**
 * Operation BillingOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingOperations
{
  public const string START_CHECKOUT = 'startBillingCheckout';

  public const string START_PORTAL = 'startBillingPortal';

  public const string CANCEL_SUBSCRIPTION = 'cancelOrganizationSubscription';

  public const string RESUME_SUBSCRIPTION = 'resumeOrganizationSubscription';

  public const string GET_SUBSCRIPTION = 'getOrganizationSubscription';

  public const string LIST_INVOICES = 'listOrganizationInvoices';

  public const string LIST_BILLING_PRICING = 'listBillingPricing';
}
