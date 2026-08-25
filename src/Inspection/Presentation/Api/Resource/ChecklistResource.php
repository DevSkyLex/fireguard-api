<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Inspection\Presentation\Api\Dto\Input\Checklist\{CreateChecklistInput, UpdateChecklistInput};
use Inspection\Presentation\Api\Dto\Output\Checklist\ChecklistOutput;
use Inspection\Presentation\Api\Operation\InspectionOperations;
use Inspection\Presentation\Api\Processor\Checklist\{ArchiveChecklistProcessor, CreateChecklistProcessor, UpdateChecklistProcessor};
use Inspection\Presentation\Api\Provider\Checklist\{GetChecklistProvider, ListChecklistsProvider};
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[ApiResource(
  shortName: 'Checklist',
  routePrefix: '/organizations',
  description: 'Versioned checklist templates for inspections.',
  operations: [
    new Post(
      name: InspectionOperations::CREATE_CHECKLIST,
      uriTemplate: '/{organizationId}/checklists',
      input: CreateChecklistInput::class,
      output: ChecklistOutput::class,
      processor: CreateChecklistProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'Create a checklist',
        description: 'Creates a new inspection checklist template.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Checklist created'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      name: InspectionOperations::LIST_CHECKLISTS,
      uriTemplate: '/{organizationId}/checklists',
      input: false,
      output: ChecklistOutput::class,
      provider: ListChecklistsProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'List checklists',
        description: 'Lists all checklists for the organization.',
        parameters: [
          new Parameter(name: 'status', in: 'query', description: 'Filter by status (active, archived)', required: false, schema: ['type' => 'string']),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Checklist list'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Get(
      name: InspectionOperations::GET_CHECKLIST,
      uriTemplate: '/{organizationId}/checklists/{checklistId}',
      input: false,
      output: ChecklistOutput::class,
      provider: GetChecklistProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'Get a checklist',
        description: 'Retrieves detailed information about a specific checklist.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Checklist details'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Checklist not found'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Post(
      name: InspectionOperations::ARCHIVE_CHECKLIST,
      uriTemplate: '/{organizationId}/checklists/{checklistId}/archive',
      input: false,
      output: ChecklistOutput::class,
      processor: ArchiveChecklistProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'Archive a checklist',
        description: 'Archives a checklist, preventing it from being used in new inspections.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Checklist archived'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Checklist not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Checklist already archived'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new Patch(
      name: InspectionOperations::UPDATE_CHECKLIST,
      uriTemplate: '/{organizationId}/checklists/{checklistId}',
      input: UpdateChecklistInput::class,
      output: ChecklistOutput::class,
      processor: UpdateChecklistProcessor::class,
      denormalizationContext: ['groups' => [InspectionSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Checklist'],
        summary: 'Update a checklist',
        description: 'Partially updates a checklist template (name, reference code, items). Archived checklists cannot be edited. Item changes are rejected once the checklist is referenced by an existing inspection, to avoid retroactively altering already-recorded inspection evidence.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Checklist updated'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Checklist not found'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'Checklist archived, referenced by existing inspections, or duplicate reference code'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
  ],
)]
final class ChecklistResource
{
}
