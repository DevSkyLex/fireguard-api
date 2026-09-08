<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response as OpenApiResponse};
use Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler;
use Equipment\Presentation\Api\Controller\ExportEquipmentsController;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource EquipmentExportResource.
 *
 * A separate resource, deliberately not an extra operation appended to
 * `EquipmentResource` — that resource's `GET_EQUIPMENT` operation
 * (`/{organizationId}/equipment/{equipmentId}`) carries no `{equipmentId}`
 * format requirement, so an `export` literal segment would collide with it.
 * Mirrors how `EquipmentKpiResource` already keeps its static
 * `/{organizationId}/equipment/kpis` route out of `EquipmentResource` for the
 * same reason.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentExport',
  routePrefix: '/organizations',
  description: 'Streams a bounded CSV export of an organization\'s equipment.',
  operations: [
    new Get(
      name: EquipmentOperations::EXPORT_EQUIPMENTS,
      description: 'Streams a bounded CSV export of every equipment item in the organization.',
      uriTemplate: '/{organizationId}/equipment/export',
      controller: ExportEquipmentsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Export equipment (CSV)',
        description: 'Streams a CSV export of every equipment item (Content-Disposition: attachment) for the given '
          . 'organization. Requires `organization.equipment.read` on the organization, resolved the same way the '
          . 'list endpoint resolves it — a resource-level ROLE_USER check alone does not grant access. The first '
          . 'six columns (type, subType, brand, model, serialNumber, locationLabel) are the import round-trip '
          . 'contract read back by the bulk CSV import; the remaining columns are read-only metadata. Bounded to '
          . ExportEquipmentsHandler::MAX_EXPORT_ROWS . ' matching rows — the request is rejected with 422 if the '
          . 'organization has more.',
        security: [['bearerAuth' => []]],
        responses: [
          Response::HTTP_OK => new OpenApiResponse(description: 'CSV export streamed successfully'),
          Response::HTTP_BAD_REQUEST => new OpenApiResponse(description: 'Missing organizationId URI parameter'),
          Response::HTTP_FORBIDDEN => new OpenApiResponse(description: 'Authenticated but missing organization.equipment.read'),
          Response::HTTP_NOT_FOUND => new OpenApiResponse(description: 'The organization is outside the caller\'s scope'),
          Response::HTTP_UNPROCESSABLE_ENTITY => new OpenApiResponse(description: 'Export exceeds the row cap'),
        ],
      ),
    ),
  ],
)]
final class EquipmentExportResource
{
}
