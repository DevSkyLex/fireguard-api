<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentKpiOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Provider\Equipment\GetEquipmentKpisProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentKpiResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentKpi',
  routePrefix: '/organizations',
  description: 'Aggregate equipment KPI counters for the organization equipment overview page.',
  operations: [
    new Get(
      name: EquipmentOperations::GET_EQUIPMENT_KPIS,
      uriTemplate: '/{organizationId}/equipment/kpis',
      input: false,
      output: EquipmentKpiOutput::class,
      provider: GetEquipmentKpisProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Get equipment KPIs',
        description: 'Returns the four headline counters for the organization equipment overview: `totalAssets` (every status), `compliant` (cross-module maintenance due status `up_to_date`), `dueSoon` (cross-module maintenance due status `due_soon`), and `openNonConformities`. The last counter is deliberately ORGANIZATION-WIDE, not equipment-scoped: non-conformities attach to inspections, not to equipment, so there is no reliable per-equipment open-non-conformity count (see `src/Equipment/MODULE.md`).',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Equipment KPIs retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid organization identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class EquipmentKpiResource
{
}
