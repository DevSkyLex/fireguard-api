<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Resource\Reporting;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use MaintenanceCost\Presentation\Api\Dto\Output\Reporting\{MaintenanceEconomicDossierOutput, MaintenanceEconomicReportOutput};
use MaintenanceCost\Presentation\Api\Operation\Reporting\MaintenanceEconomicOperations;
use MaintenanceCost\Presentation\Api\Provider\Reporting\MaintenanceEconomicProvider;

/**
 * Class MaintenanceEconomicResource
 *
 * Dedicated financial endpoints exclude ordinary operational and client reports.
 *
 * @category Resource
 */
#[ApiResource(
  shortName: 'MaintenanceEconomic',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => ['maintenance_economic:read']],
  operations: [
    new Get(name: MaintenanceEconomicOperations::REPORT, uriTemplate: '/organizations/{organizationId}/maintenance-cost/reports', output: MaintenanceEconomicReportOutput::class, provider: MaintenanceEconomicProvider::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Read exact private maintenance economics with reconciled allocations', parameters: [new Parameter(name: 'from', in: 'query', required: true, schema: ['type' => 'string', 'format' => 'date']), new Parameter(name: 'to', in: 'query', required: true, schema: ['type' => 'string', 'format' => 'date']), new Parameter(name: 'groupBy', in: 'query', schema: ['type' => 'string', 'enum' => ['equipment', 'site', 'customer']]), new Parameter(name: 'siteId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'equipmentId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'page', in: 'query', schema: ['type' => 'integer', 'minimum' => 1]), new Parameter(name: 'itemsPerPage', in: 'query', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100])], responses: [200 => new Response(description: 'Exact full-scope totals and row page'), 403 => new Response(description: 'Dedicated financial read missing'), 404 => new Response(description: 'Organization outside scope'), 422 => new Response(description: 'Invalid or excessive window; narrow filters')])),
    new GetCollection(name: MaintenanceEconomicOperations::DOSSIERS, uriTemplate: '/organizations/{organizationId}/maintenance-cost/dossiers', output: MaintenanceEconomicDossierOutput::class, provider: MaintenanceEconomicProvider::class, openapi: new Operation(tags: ['MaintenanceCost'], summary: 'Browse minimal private finance dossier identities', parameters: [new Parameter(name: 'search', in: 'query', schema: ['type' => 'string', 'maxLength' => 160]), new Parameter(name: 'from', in: 'query', schema: ['type' => 'string', 'format' => 'date']), new Parameter(name: 'to', in: 'query', schema: ['type' => 'string', 'format' => 'date']), new Parameter(name: 'siteId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'customerId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'equipmentId', in: 'query', schema: ['type' => 'string', 'format' => 'uuid']), new Parameter(name: 'page', in: 'query', schema: ['type' => 'integer', 'minimum' => 1]), new Parameter(name: 'itemsPerPage', in: 'query', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100])], responses: [200 => new Response(description: 'Hydra minimal identity page'), 403 => new Response(description: 'Dedicated financial read missing'), 404 => new Response(description: 'Organization outside scope')])),
  ],
)]
final class MaintenanceEconomicResource
{
}
