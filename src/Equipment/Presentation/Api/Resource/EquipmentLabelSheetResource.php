<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response as OpenApiResponse};
use Equipment\Presentation\Api\Controller\ExportEquipmentLabelsController;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource EquipmentLabelSheetResource.
 *
 * Server-side PDF sheet of printable QR equipment labels (Avery
 * L7159-compatible, 24 labels per A4 page), gated by
 * `organization.equipment.read` only — deliberately NOT plan-gated: the QR
 * labels are the physical half of the field scan loop (which is itself
 * ungated, like the intervention report), so gating the sheet would break
 * the core scan workflow for lower plans. The gated exports
 * (`EquipmentReportExportResource`, the safety register) are reporting
 * deliverables; a label is operational material. Each QR encodes the
 * equipment's canonical IRI `/api/equipment/{id}` — exactly the first form
 * the frontend's `InterventionDiscoveryService.normalizeScannedTarget()`
 * accepts verbatim.
 *
 * A separate resource wired through an invokable controller, `read`/`write`/
 * `deserialize`/`serialize`/`output` disabled, so API Platform's state
 * pipeline steps aside and the controller's binary `Response` is returned
 * as-is — mirrors `EquipmentReportExportResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentLabelSheet',
  routePrefix: '/organizations',
  description: 'Printable QR label sheet PDF. Requires organization.equipment.read.',
  operations: [
    new Get(
      name: EquipmentOperations::EXPORT_EQUIPMENT_LABELS,
      uriTemplate: '/{organizationId}/equipment/labels',
      controller: ExportEquipmentLabelsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Export a printable QR label sheet as PDF',
        description: 'Streams an A4 PDF of QR equipment labels laid out on an Avery L7159-compatible grid '
          . '(63.5 x 33.9 mm, 3 columns x 8 rows, 24 labels per page). Each QR encodes the equipment IRI '
          . '/api/equipment/{id}, the format the field scan resolves. Selection: ids[] (explicit equipment '
          . 'list) OR facilityId (all equipment of one facility) OR neither (the whole organization park); '
          . 'providing both is a 400. At most 500 labels per request — 422 beyond. Requires '
          . 'organization.equipment.read, resolved with the module\'s standard 403/404 split. Not plan-gated: '
          . 'labels are operational field material, not a reporting deliverable.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(
            name: 'ids[]',
            in: 'query',
            description: 'Explicit equipment identifiers to print, one label each. Mutually exclusive with facilityId.',
            required: false,
            schema: ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']],
          ),
          new Parameter(
            name: 'facilityId',
            in: 'query',
            description: 'Print one label for every equipment item of this facility. Mutually exclusive with ids[].',
            required: false,
            schema: ['type' => 'string', 'format' => 'uuid'],
          ),
        ],
        responses: [
          Response::HTTP_OK => new OpenApiResponse(description: 'QR label sheet PDF'),
          Response::HTTP_BAD_REQUEST => new OpenApiResponse(description: 'Missing organizationId, both ids[] and facilityId provided, or empty ids[]'),
          Response::HTTP_FORBIDDEN => new OpenApiResponse(description: 'Missing organization.equipment.read permission'),
          Response::HTTP_NOT_FOUND => new OpenApiResponse(description: 'Organization outside the caller\'s scope'),
          Response::HTTP_UNPROCESSABLE_ENTITY => new OpenApiResponse(description: 'Selection matches more than 500 labels'),
        ],
      ),
    ),
  ],
)]
final class EquipmentLabelSheetResource
{
}
