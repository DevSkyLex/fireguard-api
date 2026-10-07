<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Resource\Currency;

use ApiPlatform\Metadata\{ApiResource, Get, Patch};
use ApiPlatform\OpenApi\Model\Operation;
use MaintenanceCost\Presentation\Api\Dto\Input\Currency\ConfigureMaintenanceCurrencyInput;
use MaintenanceCost\Presentation\Api\Dto\Output\Currency\MaintenanceCurrencyOutput;
use MaintenanceCost\Presentation\Api\Operation\Currency\MaintenanceCurrencyOperations;
use MaintenanceCost\Presentation\Api\Processor\Currency\ConfigureMaintenanceCurrencyProcessor;
use MaintenanceCost\Presentation\Api\Provider\Currency\MaintenanceCurrencyProvider;

/** Private currency settings shared by all internal maintenance finance. */
#[ApiResource(
  shortName: 'MaintenanceCostCurrency',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['maintenance_cost_currency:read']],
  denormalizationContext: ['groups' => ['maintenance_cost_currency:write'], 'allow_extra_attributes' => false],
  operations: [
    new Get(name: MaintenanceCurrencyOperations::READ, uriTemplate: '/organizations/{organizationId}/maintenance-cost/currency', output: MaintenanceCurrencyOutput::class, provider: MaintenanceCurrencyProvider::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Read the private organization cost currency')),
    new Patch(name: MaintenanceCurrencyOperations::CONFIGURE, uriTemplate: '/organizations/{organizationId}/maintenance-cost/currency', input: ConfigureMaintenanceCurrencyInput::class, output: MaintenanceCurrencyOutput::class, read: false, status: 200, processor: ConfigureMaintenanceCurrencyProcessor::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Configure cost currency before its first financial fact')),
  ],
)]
final class MaintenanceCurrencyResource
{
}
