<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Application\UseCase\Query\ExportNonConformities\ExportNonConformitiesHandler;
use Inspection\Presentation\Api\Controller\{ExportNonConformitiesController, ExportNonConformitiesReportController};
use Inspection\Presentation\Api\Dto\Input\NonConformity\{AddNonConformityInput, UpdateNonConformityStatusInput};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Processor\NonConformity\{AddNonConformityProcessor, UpdateNonConformityStatusProcessor};
use Inspection\Presentation\Api\Provider\NonConformity\{GetNonConformityProvider, ListNonConformitiesProvider, ListOrganizationNonConformitiesProvider};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'NonConformity',
  routePrefix: '/organizations',
  description: 'Non-conformities detected during inspections.',
  operations: [
    new Post(
      name: InspectionOperations::ADD_NON_CONFORMITY,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities',
      input: AddNonConformityInput::class,
      output: NonConformityOutput::class,
      processor: AddNonConformityProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Add a non-conformity',
        description: 'Adds a non-conformity to an inspection.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Non-conformity created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_NON_CONFORMITIES,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities',
      input: false,
      output: NonConformityOutput::class,
      provider: ListNonConformitiesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List non-conformities',
        description: 'Lists non-conformities for an inspection.',
        parameters: [
          new Parameter(name: 'severity', in: 'query', description: 'Filter by severity (low, medium, high, critical)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (open, in_progress, done, waived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity list'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_ORGANIZATION_NON_CONFORMITIES,
      uriTemplate: '/{organizationId}/non-conformities',
      input: false,
      output: NonConformityOutput::class,
      provider: ListOrganizationNonConformitiesProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List organization non-conformities',
        description: 'Lists non-conformities across every inspection of an organization, newest first.',
        parameters: [
          new Parameter(name: 'severity', in: 'query', description: 'Filter by severity (low, medium, high, critical)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (open, in_progress, done, waived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity list'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::EXPORT_NON_CONFORMITIES,
      description: 'Streams a bounded CSV export of an organization\'s non-conformities using the same filter subset as the list endpoint.',
      uriTemplate: '/{organizationId}/non-conformities/export',
      controller: ExportNonConformitiesController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Export non-conformities (CSV)',
        description: 'Streams a CSV export of non-conformities (Content-Disposition: attachment) across every '
          . 'inspection of the given organization, using the same filter subset as the organization-wide list '
          . 'endpoint. Requires `organization.inspection.read` on the organization, resolved the same way the '
          . 'list endpoint resolves it — a resource-level ROLE_USER check alone does not grant access. Bounded '
          . 'to ' . ExportNonConformitiesHandler::MAX_EXPORT_ROWS . ' matching rows — the request is rejected '
          . 'with 422 if the filters match more; narrow the filters and retry.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'severity', in: 'query', description: 'Filter by severity (low, medium, high, critical)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (open, in_progress, done, waived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'CSV export streamed successfully'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing organizationId or an invalid enum filter value'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Authenticated but missing organization.inspection.read'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'The organization is outside the caller\'s scope'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Export exceeds the row cap; narrow the filters and retry'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::EXPORT_NON_CONFORMITIES_REPORT,
      description: 'Streams a PDF report of an organization\'s non-conformities grouped by severity, using the same filter subset as the CSV export.',
      uriTemplate: '/{organizationId}/non-conformities/report',
      controller: ExportNonConformitiesReportController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Export non-conformities report (PDF)',
        description: 'Streams a PDF report of non-conformities (Content-Disposition: attachment) across every '
          . 'inspection of the given organization, grouped by severity, using the same severity/status filter '
          . 'subset as the CSV export. Requires `organization.inspection.read` on the organization, resolved '
          . 'with the module\'s standard 403/404 split, AND a pro/max plan — the same entitlement gate as the '
          . 'regulatory safety register export. Bounded to ' . ExportNonConformitiesHandler::MAX_EXPORT_ROWS
          . ' matching rows — the request is rejected with 422 if the filters match more; narrow the filters '
          . 'and retry.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'severity', in: 'query', description: 'Filter by severity (low, medium, high, critical)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (open, in_progress, done, waived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformities report PDF'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Missing organizationId or an invalid enum filter value'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Missing organization.inspection.read, or plan not entitled (pro/max required)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'The organization is outside the caller\'s scope'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'Report exceeds the row cap; narrow the filters and retry'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::GET_NON_CONFORMITY,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities/{nonConformityId}',
      input: false,
      output: NonConformityOutput::class,
      provider: GetNonConformityProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Get a non-conformity',
        description: 'Retrieves one non-conformity for an inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Non-conformity details'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Non-conformity not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Patch(
      name: InspectionOperations::UPDATE_NON_CONFORMITY_STATUS,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/non-conformities/{nonConformityId}/status',
      read: false,
      input: UpdateNonConformityStatusInput::class,
      output: NonConformityOutput::class,
      processor: UpdateNonConformityStatusProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Update non-conformity status',
        description: 'Updates the status of a non-conformity.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Status updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Non-conformity not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Non-conformity already resolved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class NonConformityResource
{
}
