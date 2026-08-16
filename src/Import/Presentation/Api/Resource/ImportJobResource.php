<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody, Response};
use ArrayObject;
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Processor\CreateImportJobProcessor;
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
        description: 'Uploads a CSV file to provision Equipment or Facility resources '
          . 'in bulk; a row failure is reported, not fatal to the batch.',
        requestBody: new RequestBody(
          content: new ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'organization' => ['type' => 'string', 'description' => 'Organization IRI.'],
                  'kind' => ['type' => 'string', 'enum' => ['equipment', 'facility']],
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
      paginationItemsPerPage: 30,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'organization', in: 'query', description: 'Organization IRI.', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'kind', in: 'query', description: 'Resource kind filter (equipment|facility).', required: false, schema: ['type' => 'string']),
      ]),
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
