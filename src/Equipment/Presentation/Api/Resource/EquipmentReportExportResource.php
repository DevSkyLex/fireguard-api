<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response as OpenApiResponse};
use Equipment\Presentation\Api\Controller\ExportEquipmentReportController;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource EquipmentReportExportResource.
 *
 * Server-side PDF "equipment sheet" export, gated by
 * `organization.equipment.read` AND the organization's plan tier
 * (`pro`/`max` only — the SAME entitlement gate as the Compliance safety
 * register, a distinct 403 is raised otherwise). A separate resource,
 * deliberately not an extra operation appended to `EquipmentResource` —
 * mirrors `EquipmentExportResource`'s rationale. Wired through an invokable
 * controller rather than a Provider since the operation returns a raw
 * binary `Response`, not a serialized resource: `read`, `write`,
 * `deserialize`, `serialize` and `output` are disabled so API Platform's
 * state pipeline steps aside and the controller's `Response` is returned
 * as-is — mirrors `Compliance\...\SafetyRegisterExportResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentReportExport',
  routePrefix: '/organizations',
  description: 'PDF equipment sheet export. Requires organization.equipment.read AND a pro/max plan.',
  operations: [
    new Get(
      name: EquipmentOperations::EXPORT_EQUIPMENT_REPORT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/report',
      controller: ExportEquipmentReportController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Export an equipment sheet as PDF',
        description: 'Streams a PDF sheet of one equipment item (identity, maintenance history, attachment index) '
          . 'with Content-Disposition: attachment. Requires organization.equipment.read, resolved with the '
          . 'module\'s standard 403/404 split, AND a pro/max plan — the same entitlement gate as the regulatory '
          . 'safety register export.',
        security: [['bearerAuth' => []]],
        responses: [
          Response::HTTP_OK => new OpenApiResponse(description: 'Equipment sheet PDF'),
          Response::HTTP_BAD_REQUEST => new OpenApiResponse(description: 'Missing organizationId or equipmentId URI parameter'),
          Response::HTTP_FORBIDDEN => new OpenApiResponse(description: 'Missing organization.equipment.read, or plan not entitled (pro/max required)'),
          Response::HTTP_NOT_FOUND => new OpenApiResponse(description: 'Organization outside the caller\'s scope, or equipment not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentReportExportResource
{
}
