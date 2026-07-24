<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\Operation;
use Billing\Presentation\Api\Dto\Output\PlanPricingOutput;
use Billing\Presentation\Api\Operation\BillingOperations;
use Billing\Presentation\Api\Provider\GetPricingProvider;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;

/**
 * Resource BillingPricingResource.
 *
 * Public-to-members pricing catalog: the display amounts for each payable plan,
 * joined by the frontend with the plan catalog to render plan cards.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'BillingPricing',
  routePrefix: '/billing',
  description: 'Subscription plan pricing.',
  operations: [
    new GetCollection(
      name: BillingOperations::LIST_BILLING_PRICING,
      uriTemplate: '/pricing',
      input: false,
      output: PlanPricingOutput::class,
      provider: GetPricingProvider::class,
      normalizationContext: ['groups' => [BillingSerializationGroup::READ]],
      paginationEnabled: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Billing'],
        summary: 'List plan pricing',
        description: 'Returns the display pricing (monthly and yearly amounts) of every payable plan.',
      ),
    ),
  ],
)]
final class BillingPricingResource
{
}
