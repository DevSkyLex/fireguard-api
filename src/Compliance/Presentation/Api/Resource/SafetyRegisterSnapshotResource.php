<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\Operation;
use Compliance\Presentation\Api\Controller\DownloadSafetyRegisterSnapshotController;
use Compliance\Presentation\Api\Dto\Input\Snapshot\CreateSafetyRegisterSnapshotInput;
use Compliance\Presentation\Api\Dto\Output\Snapshot\SafetyRegisterSnapshotOutput;
use Compliance\Presentation\Api\Operation\ComplianceOperations;
use Compliance\Presentation\Api\Processor\Snapshot\CreateSafetyRegisterSnapshotProcessor;
use Compliance\Presentation\Api\Provider\Snapshot\ListSafetyRegisterSnapshotsProvider;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource SafetyRegisterSnapshotResource.
 *
 * Dated, immutable archives of the regulatory "registre de sécurité" PDF.
 * Gated exactly like the live export: `organization.compliance.export` AND
 * the organization's plan tier (`pro`/`max` only — a distinct 403 is raised
 * otherwise), with `resolveAccess` answering 404 for an organization outside
 * the caller's scope. The live `…/compliance/export` endpoint is untouched;
 * snapshots add the "prove what the register said on that date" capability.
 * The download operation is wired through an invokable controller since it
 * returns a raw binary `Response`, mirroring `SafetyRegisterExportResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'SafetyRegisterSnapshot',
  routePrefix: '/organizations',
  description: 'Dated archives of the regulatory "registre de sécurité" PDF. Requires organization.compliance.export AND a pro/max plan.',
  operations: [
    new Post(
      name: ComplianceOperations::CREATE_SAFETY_REGISTER_SNAPSHOT,
      uriTemplate: '/{organizationId}/compliance/register-snapshots',
      status: HttpResponse::HTTP_CREATED,
      input: CreateSafetyRegisterSnapshotInput::class,
      output: SafetyRegisterSnapshotOutput::class,
      processor: CreateSafetyRegisterSnapshotProcessor::class,
      read: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Compliance'],
        summary: 'Archive the safety register as a dated snapshot',
        description: 'Renders the register through the same pipeline as the live export and stores the PDF '
          . 'with its SHA-256 content hash. Pass facilityId for a facility-scoped register; omit it for the '
          . 'organization-wide one.',
      ),
    ),
    new GetCollection(
      name: ComplianceOperations::LIST_SAFETY_REGISTER_SNAPSHOTS,
      uriTemplate: '/{organizationId}/compliance/register-snapshots',
      output: SafetyRegisterSnapshotOutput::class,
      provider: ListSafetyRegisterSnapshotsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Compliance'],
        summary: 'List archived safety register snapshots',
        description: 'Organization-scoped snapshot metadata, most recently generated first.',
      ),
    ),
    new Get(
      name: ComplianceOperations::DOWNLOAD_SAFETY_REGISTER_SNAPSHOT,
      uriTemplate: '/{organizationId}/compliance/register-snapshots/{snapshotId}/download',
      controller: DownloadSafetyRegisterSnapshotController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Compliance'],
        summary: 'Download an archived safety register snapshot PDF',
      ),
    ),
  ],
)]
final class SafetyRegisterSnapshotResource
{
}
