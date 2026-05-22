<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, GetCollection};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Dto\Output\Equipment\MaintenanceLogOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Provider\Equipment\ListMaintenanceLogsProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentMaintenanceLogResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentMaintenanceLog',
  routePrefix: '/organizations',
  description: 'Maintenance windows recorded for equipment items.',
  operations: [
    new GetCollection(
      name: EquipmentOperations::LIST_MAINTENANCE_LOGS,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/maintenance-logs',
      input: false,
      output: MaintenanceLogOutput::class,
      provider: ListMaintenanceLogsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationItemsPerPage: 20,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List maintenance logs',
        description: 'Lists the maintenance windows recorded for a given equipment item, ordered by start date descending.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Maintenance logs retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentMaintenanceLogResource
{
}
