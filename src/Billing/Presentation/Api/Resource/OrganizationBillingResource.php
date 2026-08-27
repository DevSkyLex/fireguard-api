<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Billing\Presentation\Api\Dto\Input\StartCheckoutInput;
use Billing\Presentation\Api\Dto\Output\{CheckoutSessionOutput, PaymentMethodOutput, PortalSessionOutput, SubscriptionOutput};
use Billing\Presentation\Api\Operation\BillingOperations;
use Billing\Presentation\Api\Processor\{CancelSubscriptionProcessor, ResumeSubscriptionProcessor, StartCheckoutProcessor, StartPortalProcessor};
use Billing\Presentation\Api\Provider\{GetPaymentMethodProvider, GetSubscriptionProvider};
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource OrganizationBillingResource.
 *
 * Organization-scoped Stripe billing entry points: start a hosted Checkout, open
 * the Billing Portal, and read the current subscription state.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'OrganizationBilling',
  routePrefix: '/organizations',
  description: 'Organization subscription billing through Stripe.',
  operations: [
    new Post(
      name: BillingOperations::START_CHECKOUT,
      uriTemplate: '/{organizationId}/billing/checkout',
      read: false,
      input: StartCheckoutInput::class,
      output: CheckoutSessionOutput::class,
      processor: StartCheckoutProcessor::class,
      denormalizationContext: ['groups' => [BillingSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Start subscription checkout',
        description: 'Creates a hosted Stripe Checkout session for the chosen paid plan and cadence and returns its URL. Requires the organization.settings.write permission.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Checkout session created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Plan not available for purchase'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: BillingOperations::START_PORTAL,
      uriTemplate: '/{organizationId}/billing/portal',
      read: false,
      input: false,
      output: PortalSessionOutput::class,
      processor: StartPortalProcessor::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Open Billing Portal',
        description: 'Creates a hosted Stripe Billing Portal session and returns its URL. Requires the organization.settings.write permission.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Portal session created'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'No billing customer for the organization'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: BillingOperations::CANCEL_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/billing/cancel',
      status: HttpResponse::HTTP_OK,
      read: false,
      input: false,
      output: SubscriptionOutput::class,
      processor: CancelSubscriptionProcessor::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Cancel subscription at period end',
        description: 'Schedules cancellation of the organization subscription at the end of the current billing period and returns the refreshed state. Requires the organization.settings.write permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Cancellation scheduled'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'No active subscription to cancel'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: BillingOperations::RESUME_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/billing/resume',
      status: HttpResponse::HTTP_OK,
      read: false,
      input: false,
      output: SubscriptionOutput::class,
      processor: ResumeSubscriptionProcessor::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Resume a scheduled cancellation',
        description: 'Clears a scheduled cancellation so the organization subscription renews normally, and returns the refreshed state. Requires the organization.settings.write permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Cancellation cleared'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'No subscription to resume'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: BillingOperations::GET_SUBSCRIPTION,
      uriTemplate: '/{organizationId}/billing/subscription',
      input: false,
      output: SubscriptionOutput::class,
      provider: GetSubscriptionProvider::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Get organization subscription',
        description: 'Returns the current Stripe subscription state of the organization. Requires the organization.read permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Subscription state returned'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: BillingOperations::GET_PAYMENT_METHOD,
      uriTemplate: '/{organizationId}/billing/payment-method',
      input: false,
      output: PaymentMethodOutput::class,
      provider: GetPaymentMethodProvider::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'Get organization payment method',
        description: 'Returns the organization\'s saved Stripe card (brand, last 4 digits, expiry). Card details are never collected or transmitted by this API — read only, sourced from Stripe. To change the card, start a hosted Billing Portal session. Requires the organization.read permission.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Payment method state returned (hasPaymentMethod may be false)'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_SERVICE_UNAVAILABLE => new Response(description: 'The Stripe gateway could not be reached'),
        ],
      ),
    ),
  ],
)]
final class OrganizationBillingResource
{
}
