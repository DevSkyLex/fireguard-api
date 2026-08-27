<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Application\UseCase\Query\ExportInspections\ExportInspectionsHandler;
use Inspection\Presentation\Api\Controller\ExportInspectionsController;
use Inspection\Presentation\Api\Dto\Input\Inspection\{CreateInspectionInput, EditInspectionInput};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Processor\Inspection\{
  CancelInspectionProcessor,
  CloseInspectionProcessor,
  CreateInspectionProcessor,
  EditInspectionProcessor,
  SubmitInspectionProcessor
};
use Inspection\Presentation\Api\Provider\Inspection\{GetInspectionProvider, ListInspectionsProvider};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'LegacyInspection',
  routePrefix: '/organizations',
  description: 'Inspection records for fire safety equipment.',
  operations: [
    new Post(
      name: InspectionOperations::CREATE_INSPECTION,
      uriTemplate: '/{organizationId}/inspections',
      input: CreateInspectionInput::class,
      output: InspectionOutput::class,
      processor: CreateInspectionProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Create an inspection',
        description: 'Creates a new inspection for an equipment item.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Inspection created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_INSPECTIONS,
      uriTemplate: '/{organizationId}/inspections',
      input: false,
      output: InspectionOutput::class,
      provider: ListInspectionsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      // 200 rather than the 100 every other collection caps at: the facility overview previews a site's inspections in one page,
      // and a lower ceiling would silently drop rows past the cut rather than fail.
      paginationMaximumItemsPerPage: 200,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspections',
        description: 'Lists all inspections for the organization.',
        parameters: [
          new Parameter(name: 'equipmentId', in: 'query', description: 'Filter by equipment', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'facilityId', in: 'query', description: 'Filter by facility', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'result', in: 'query', description: 'Filter by result (pass, fail, partial)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (draft, submitted, closed)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'performedAtFrom', in: 'query', description: 'Filter inspections performed on or after this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'performedAtTo', in: 'query', description: 'Filter inspections performed on or before this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'inspectorUserId', in: 'query', description: 'Filter by inspector user identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
          new Parameter(name: 'checklistId', in: 'query', description: 'Filter by checklist identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection list'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_FACILITY_INSPECTIONS,
      uriTemplate: '/{organizationId}/facilities/{facilityId}/inspections',
      input: false,
      output: InspectionOutput::class,
      provider: ListInspectionsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List facility inspections',
        description: 'Lists inspections directly linked to one facility.',
        parameters: [
          new Parameter(name: 'equipmentId', in: 'query', description: 'Filter by equipment', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'result', in: 'query', description: 'Filter by result (pass, fail, partial)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (draft, submitted, closed)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'performedAtFrom', in: 'query', description: 'Filter inspections performed on or after this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'performedAtTo', in: 'query', description: 'Filter inspections performed on or before this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'inspectorUserId', in: 'query', description: 'Filter by inspector user identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
          new Parameter(name: 'checklistId', in: 'query', description: 'Filter by checklist identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Facility inspection list'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::EXPORT_INSPECTIONS,
      description: 'Streams a bounded CSV export of inspections using the same filter subset as the list endpoint.',
      uriTemplate: '/{organizationId}/inspections/export',
      controller: ExportInspectionsController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Export inspections (CSV)',
        description: 'Streams a CSV export of inspections (Content-Disposition: attachment) for the given '
          . 'organization, using the same filter subset as the list endpoint. Requires '
          . '`organization.inspection.read` on the organization, resolved the same way the list endpoint '
          . 'resolves it — a resource-level ROLE_USER check alone does not grant access. Bounded to '
          . ExportInspectionsHandler::MAX_EXPORT_ROWS . ' matching rows — the request is rejected with 422 if '
          . 'the filters match more; narrow the filters and retry.',
        security: [['bearerAuth' => []]],
        parameters: [
          new Parameter(name: 'equipmentId', in: 'query', description: 'Filter by equipment', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'facilityId', in: 'query', description: 'Filter by facility', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'result', in: 'query', description: 'Filter by result (pass, fail, partial)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (draft, submitted, closed, cancelled)', required: false, schema: ['type' => 'string']),
          new Parameter(name: 'performedAtFrom', in: 'query', description: 'Filter inspections performed on or after this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'performedAtTo', in: 'query', description: 'Filter inspections performed on or before this instant.', required: false, schema: ['type' => 'string', 'format' => 'date-time']),
          new Parameter(name: 'inspectorUserId', in: 'query', description: 'Filter by inspector user identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
          new Parameter(name: 'checklistId', in: 'query', description: 'Filter by checklist identifier.', required: false, schema: ['type' => 'string', 'format' => 'uuid']),
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
      name: InspectionOperations::GET_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}',
      requirements: ['inspectionId' => self::UUID_PATTERN],
      input: false,
      output: InspectionOutput::class,
      provider: GetInspectionProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Get an inspection',
        description: 'Retrieves detailed information about a specific inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection details'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Patch(
      name: InspectionOperations::EDIT_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}',
      requirements: ['inspectionId' => self::UUID_PATTERN],
      input: EditInspectionInput::class,
      output: InspectionOutput::class,
      processor: EditInspectionProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Patch an inspection',
        description: 'Partially updates a draft inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection is no longer editable'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: InspectionOperations::SUBMIT_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/submit',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: InspectionOutput::class,
      processor: SubmitInspectionProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Submit an inspection',
        description: 'Submits a draft inspection for review.',
        deprecated: true,
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection submitted'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection already closed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: InspectionOperations::CLOSE_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}/close',
      status: HttpResponse::HTTP_OK,
      input: false,
      output: InspectionOutput::class,
      processor: CloseInspectionProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Close an inspection',
        description: 'Closes a submitted inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Inspection closed'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection already closed'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Delete(
      name: InspectionOperations::CANCEL_INSPECTION,
      uriTemplate: '/{organizationId}/inspections/{inspectionId}',
      requirements: ['inspectionId' => self::UUID_PATTERN],
      input: false,
      output: false,
      read: false,
      processor: CancelInspectionProcessor::class,
      status: HttpResponse::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Cancel an inspection',
        description: 'Deletes a draft inspection.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Inspection cancelled'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Inspection is no longer cancellable'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
/**
 * Resource InspectionResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResource
{
  // #region Constants
  /**
   * Constant UUID_PATTERN.
   *
   * Disambiguates `{inspectionId}` from the static `/inspections/export`
   * route: without a requirement, `export` matches `{inspectionId}` too, and
   * whichever operation the attribute scanner discovers first wins the
   * route — not a stable thing to depend on. Mirrors
   * `Intervention\Presentation\Api\Resource\InterventionResource`'s `{id}`
   * requirement.
   *
   * @var string
   */
  private const string UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
  // #endregion
}
