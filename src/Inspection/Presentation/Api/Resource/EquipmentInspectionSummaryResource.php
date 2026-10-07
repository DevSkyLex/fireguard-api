<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use Inspection\Presentation\Api\Dto\Output\Equipment\EquipmentInspectionSummaryOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Provider\Equipment\GetEquipmentInspectionSummaryProvider;

/**
 * Resource EquipmentInspectionSummaryResource.
 *
 * @category Resource
 */
#[ApiResource(
  shortName: 'EquipmentInspectionSummary',
  routePrefix: '/organizations',
  operations: [new Get(
    name: InspectionOperations::GET_EQUIPMENT_INSPECTION_SUMMARY,
    uriTemplate: '/{organizationId}/equipment/{equipmentId}/inspection-summary',
    input: false,
    output: EquipmentInspectionSummaryOutput::class,
    provider: GetEquipmentInspectionSummaryProvider::class,
    security: "is_granted('ROLE_USER')",
    description: 'Published controls and open findings for one equipment; requires both equipment and inspection read permissions.',
  )],
)]
final class EquipmentInspectionSummaryResource
{
}
