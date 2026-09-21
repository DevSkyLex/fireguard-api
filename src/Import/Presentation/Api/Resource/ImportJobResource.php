<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody, Response};
use ArrayObject;
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Processor\{ConfirmImportSimulationProcessor, CreateImportJobProcessor, ResumeImportJobProcessor};
use Import\Presentation\Api\Provider\{ImportJobCollectionProvider, ImportJobProvider};
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource ImportJobResource.
 *
 * Bulk CSV import batches: upload once (`POST /imports`, multipart), track
 * status and the per-row error report (`GET /imports/{id}`), list an
 * organization's imports (`GET /imports`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'ImportJob',
  description: 'Bulk CSV import batches provisioning Equipment or Facility resources.',
  operations: [
    new Post(
      uriTemplate: '/imports/{id}/confirm',
      name: 'confirm_import_simulation',
      status: HttpResponse::HTTP_ACCEPTED,
      read: false,
      deserialize: false,
      input: false,
      output: ImportJobOutput::class,
      processor: ConfirmImportSimulationProcessor::class,
      security: "is_granted('ROLE_USER')",
      strictQueryParameterValidation: true,
      parameters: new \ApiPlatform\Metadata\Parameters(),
      openapi: new Operation(
        tags: ['Import'],
        summary: 'Confirm a successful simulation once',
        description: 'Reuses the retained file. Repeated confirmation returns the same real import. Rights, quotas and references are checked again during execution.',
        responses: [
          202 => new Response(description: 'The single real import, newly enqueued or already confirmed'),
          403 => new Response(description: 'Write permission removed'),
          404 => new Response(description: 'Simulation absent or outside membership scope'),
          409 => new Response(description: 'Simulation incomplete, contains row failures, or retained file unavailable'),
        ],
      ),
    ),
    new Post(
      uriTemplate: '/imports/{id}/resume',
      name: 'resume_import_job',
      status: HttpResponse::HTTP_ACCEPTED,
      read: false,
      deserialize: false,
      input: false,
      output: ImportJobOutput::class,
      processor: ResumeImportJobProcessor::class,
      security: "is_granted('ROLE_USER')",
      strictQueryParameterValidation: true,
      parameters: new \ApiPlatform\Metadata\Parameters(),
      openapi: new Operation(
        tags: ['Import'],
        summary: 'Resume the same import from its confirmed rows',
        responses: [
          202 => new Response(description: 'Existing import enqueued; confirmed rows retained'),
          403 => new Response(description: 'Missing write permission for this import kind'),
          404 => new Response(description: 'Import absent or outside the active membership scope'),
          409 => new Response(description: 'Import already completed or reserved by a live worker'),
        ],
      ),
    ),
    new Post(
      uriTemplate: '/imports',
      status: HttpResponse::HTTP_ACCEPTED,
      deserialize: false,
      input: false,
      output: ImportJobOutput::class,
      processor: CreateImportJobProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Import'],
        summary: 'Upload a bulk CSV import',
        description: 'Uploads a CSV file to provision Equipment or Facility resources, or '
          . 'member invitations, in bulk; a row failure is reported, not fatal to the batch.',
        requestBody: new RequestBody(
          content: new ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'organization' => ['type' => 'string', 'description' => 'Organization IRI.'],
                  'kind' => ['type' => 'string', 'enum' => ['equipment', 'facility', 'member']],
                  'file' => ['type' => 'string', 'format' => 'binary'],
                  'dryRun' => [
                    'type' => 'boolean',
                    'description' => 'Validate, resolve parent-by-code and project the plan quota '
                      . 'without provisioning anything; the report is returned via GET /imports/{id}.',
                  ],
                ],
                'required' => ['organization', 'kind', 'file'],
              ],
            ],
          ]),
        ),
        responses: [
          HttpResponse::HTTP_ACCEPTED => new Response(description: 'Import job accepted, processing enqueued'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'MIME type, extension or size rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
        ],
      ),
    ),
    new GetCollection(
      uriTemplate: '/imports',
      output: ImportJobOutput::class,
      provider: ImportJobCollectionProvider::class,
      paginationEnabled: true,
      paginationClientItemsPerPage: true,
      paginationMaximumItemsPerPage: 100,
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      parameters: [
        'organization' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Organization IRI.',
          required: true,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        ),
        'kind' => new \ApiPlatform\Metadata\QueryParameter(
          schema: ['type' => 'string'],
          description: 'Resource kind filter (equipment|facility|member).',
          required: false,
          castToArray: false,
          castToNativeType: false,
          constraints: [],
          openApi: new Parameter(name: 'kind', in: 'query', description: 'Resource kind filter (equipment|facility|member).', required: false, schema: ['type' => 'string']),
        ),
      ],
      openapi: new Operation(parameters: []),
    ),
    new Get(
      uriTemplate: '/imports/{id}',
      output: ImportJobOutput::class,
      provider: ImportJobProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class ImportJobResource
{
}
