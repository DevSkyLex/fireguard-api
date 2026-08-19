<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Intervention\Presentation\Api\Controller\ExportInterventionReportController;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InterventionReportExportResource.
 *
 * Server-side PDF export of one intervention's report, gated entirely by
 * `organization.interventions.read` (enforced inside
 * `GetInterventionWorkflowHandler`, the same handler `GET /interventions/{id}`
 * uses) — no phase gate, mirroring the attachment-download precedent
 * documented in `src/Intervention/MODULE.md`. Wired through an invokable
 * controller rather than a Provider since the operation returns a raw binary
 * `Response`, not a serialized resource: `read`, `write`, `deserialize`,
 * `serialize` and `output` are disabled so API Platform's state pipeline
 * steps aside and the controller's `Response` is returned as-is — mirrors
 * `Compliance\...\SafetyRegisterExportResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionReportExport',
  description: 'PDF report export of one intervention. Requires organization.interventions.read.',
  operations: [
    new Get(
      name: InterventionOperations::EXPORT_INTERVENTION_REPORT,
      uriTemplate: '/interventions/{id}/report',
      controller: ExportInterventionReportController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Intervention'],
        summary: 'Export an intervention report as PDF',
        description: 'Streams a PDF report of the intervention with Content-Disposition: attachment. '
          . 'Gated by the flat organization.interventions.read permission — no phase restriction, '
          . 'so the report is available for an intervention in any status.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Intervention report PDF'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires organization.interventions.read)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Intervention not found, or belongs to another organization'),
        ],
      ),
    ),
  ],
)]
final class InterventionReportExportResource
{
}
