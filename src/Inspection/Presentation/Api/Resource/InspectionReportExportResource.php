<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response as OpenApiResponse};
use Inspection\Presentation\Api\Controller\ExportInspectionReportController;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InspectionReportExportResource.
 *
 * Server-side PDF export of one inspection's report, gated by
 * `organization.inspection.read` AND the organization's plan tier
 * (`pro`/`max` only — the SAME entitlement gate as the Compliance safety
 * register, a distinct 403 is raised otherwise). Routed under the module's
 * standard org-scoped convention
 * (`/organizations/{organizationId}/inspections/{inspectionId}/report`) —
 * every Inspection single-record route carries the `organizationId`, and
 * every read query requires it, so the Intervention-style bare
 * `/inspections/{id}/report` shape was deliberately not copied. Wired
 * through an invokable controller rather than a Provider since the
 * operation returns a raw binary `Response`, not a serialized resource:
 * `read`, `write`, `deserialize`, `serialize` and `output` are disabled so
 * API Platform's state pipeline steps aside and the controller's `Response`
 * is returned as-is — mirrors `Compliance\...\SafetyRegisterExportResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InspectionReportExport',
  routePrefix: '/organizations',
  description: 'PDF report export of one inspection. Requires organization.inspection.read AND a pro/max plan.',
  operations: [
    new Get(
      name: InspectionOperations::EXPORT_INSPECTION_REPORT,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/report',
      controller: ExportInspectionReportController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Export an inspection report as PDF',
        description: 'Streams a PDF report of one inspection (identity, checklist responses, non-conformities) with '
          . 'Content-Disposition: attachment. Requires organization.inspection.read, resolved with the module\'s '
          . 'standard 403/404 split, AND a pro/max plan — the same entitlement gate as the regulatory safety '
          . 'register export.',
        security: [['bearerAuth' => []]],
        responses: [
          Response::HTTP_OK => new OpenApiResponse(description: 'Inspection report PDF'),
          Response::HTTP_BAD_REQUEST => new OpenApiResponse(description: 'Missing organizationId or inspectionId URI parameter'),
          Response::HTTP_FORBIDDEN => new OpenApiResponse(description: 'Missing organization.inspection.read, or plan not entitled (pro/max required)'),
          Response::HTTP_NOT_FOUND => new OpenApiResponse(description: 'Organization outside the caller\'s scope, or inspection not found'),
        ],
      ),
    ),
  ],
)]
final class InspectionReportExportResource
{
}
