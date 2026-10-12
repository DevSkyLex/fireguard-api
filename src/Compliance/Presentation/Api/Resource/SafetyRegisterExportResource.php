<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response as OpenApiResponse};
use ArrayObject;
use Compliance\Presentation\Api\Controller\ExportSafetyRegisterController;
use Compliance\Presentation\Api\Operation\ComplianceOperations;

/**
 * Resource SafetyRegisterExportResource.
 *
 * Server-side PDF "registre de sécurité" export, gated by
 * `organization.compliance.export` AND the organization's plan tier
 * (`pro`/`max` only — a distinct 403 is raised otherwise). Wired through an
 * invokable controller rather than a Provider/Processor since the operation
 * returns a raw binary `Response`, not a serialized resource: `read`,
 * `write`, `deserialize`, and `serialize` are disabled so API Platform's
 * state pipeline steps aside and the controller's `Response` is returned
 * as-is (the same mechanism API Platform's own built-in actions use for
 * non-resource responses).
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'SafetyRegisterExport',
  routePrefix: '/organizations',
  description: 'Regulatory "registre de sécurité" PDF export. Requires organization.compliance.export AND a pro/max plan.',
  operations: [
    new Get(
      name: ComplianceOperations::EXPORT_ORGANIZATION_SAFETY_REGISTER,
      uriTemplate: '/{organizationId}/compliance/export',
      controller: ExportSafetyRegisterController::class,
      status: 200,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        responses: [
          200 => new OpenApiResponse(
            description: 'Safety register PDF attachment',
            content: new ArrayObject(['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]),
          ),
        ],
      ),
    ),
    new Get(
      name: ComplianceOperations::EXPORT_FACILITY_SAFETY_REGISTER,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/compliance/export',
      controller: ExportSafetyRegisterController::class,
      status: 200,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        responses: [
          200 => new OpenApiResponse(
            description: 'Safety register PDF attachment',
            content: new ArrayObject(['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]),
          ),
        ],
      ),
    ),
  ],
)]
final class SafetyRegisterExportResource
{
}
