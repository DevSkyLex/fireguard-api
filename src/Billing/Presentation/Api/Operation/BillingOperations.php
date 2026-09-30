<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Operation;

/**
 * Operation BillingOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingOperations
{
  /**
   * Constant START_CHECKOUT
   */
  public const string START_CHECKOUT = 'startBillingCheckout';

  /**
   * Constant START_PORTAL
   */
  public const string START_PORTAL = 'startBillingPortal';

  /**
   * Constant CANCEL_SUBSCRIPTION
   */
  public const string CANCEL_SUBSCRIPTION = 'cancelOrganizationSubscription';

  /**
   * Constant RESUME_SUBSCRIPTION
   */
  public const string RESUME_SUBSCRIPTION = 'resumeOrganizationSubscription';

  /**
   * Constant GET_SUBSCRIPTION
   */
  public const string GET_SUBSCRIPTION = 'getOrganizationSubscription';

  /**
   * Constant GET_PAYMENT_METHOD
   */
  public const string GET_PAYMENT_METHOD = 'getOrganizationPaymentMethod';

  /**
   * Constant LIST_INVOICES
   */
  public const string LIST_INVOICES = 'listOrganizationInvoices';

  /**
   * Constant LIST_BILLING_PRICING
   */
  public const string LIST_BILLING_PRICING = 'listBillingPricing';
}
