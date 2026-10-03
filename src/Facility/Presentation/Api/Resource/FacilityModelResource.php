<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Patch, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody, Response};
use ArrayObject;
use Facility\Presentation\Api\Controller\DownloadFacilityModelController;
use Facility\Presentation\Api\Dto\Input\Model\UpdateFacilityModelInput;
use Facility\Presentation\Api\Dto\Output\Model\FacilityModelOutput;
use Facility\Presentation\Api\Operation\FacilityModelOperations;
use Facility\Presentation\Api\Processor\Model\FacilityModelProcessor;
use Facility\Presentation\Api\Provider\Model\FacilityModelProvider;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;

/**
 * Resource FacilityModelResource.
 *
 * @category Resource
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityModel',
  description: 'Immutable self-contained GLB 2.0 building models; maximum 10 MiB and two models per building.',
  security: "is_granted('ROLE_USER')",
  normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
  denormalizationContext: ['groups' => [FacilitySerializationGroup::WRITE]],
  operations: [
    new Post(
      name: FacilityModelOperations::UPLOAD,
      uriTemplate: '/organizations/{organizationId}/facilities/{buildingId}/models',
      status: 201,
      read: false,
      deserialize: false,
      input: false,
      output: FacilityModelOutput::class,
      processor: FacilityModelProcessor::class,
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Upload a self-contained GLB 2.0 model',
        requestBody: new RequestBody(required: true, content: new ArrayObject([
          'multipart/form-data' => ['schema' => ['type' => 'object', 'required' => ['file'],
            'properties' => ['file' => ['type' => 'string', 'format' => 'binary']]]],
        ])),
        responses: [201 => new Response(description: 'Draft model uploaded'), 403 => new Response(description: 'Missing facilities write permission'),
          404 => new Response(description: 'Building outside organization scope or missing'), 409 => new Response(description: 'Two models already exist'),
          422 => new Response(description: 'Invalid GLB, unsupported required extension or file over 10 MiB')],
      ),
    ),
    new GetCollection(
      name: FacilityModelOperations::LIST,
      uriTemplate: '/organizations/{organizationId}/facilities/{buildingId}/models',
      paginationEnabled: false,
      output: FacilityModelOutput::class,
      provider: FacilityModelProvider::class,
      openapi: new Operation(tags: ['Facility'], summary: 'List a building active and draft models', responses: [
        200 => new Response(description: 'Building model collection'), 403 => new Response(description: 'Missing facilities read permission'),
        404 => new Response(description: 'Building outside organization scope or missing'),
      ]),
    ),
    new Get(
      name: FacilityModelOperations::GET,
      uriTemplate: '/facility-models/{id}',
      output: FacilityModelOutput::class,
      provider: FacilityModelProvider::class,
      openapi: new Operation(tags: ['Facility'], summary: 'Read model metadata, transformation and node associations', responses: [
        200 => new Response(description: 'Model metadata with revision ETag'), 403 => new Response(description: 'Missing facilities read permission'),
        404 => new Response(description: 'Model outside organization scope or missing'),
      ]),
    ),
    new Patch(
      name: FacilityModelOperations::UPDATE,
      uriTemplate: '/facility-models/{id}',
      status: 200,
      read: false,
      input: UpdateFacilityModelInput::class,
      output: FacilityModelOutput::class,
      processor: FacilityModelProcessor::class,
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Update a model transformation and associations',
        description: 'Requires If-Match: "revision-N" and a complete transform. Omitted or null bindings preserve stored associations; unavailable historical nodes remain until explicitly removed or reassigned. New associations must belong to the nearest building frame.',
        parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'pattern' => '^"revision-[0-9]+"$'])],
        requestBody: new RequestBody(required: true, content: new ArrayObject([
          'application/merge-patch+json' => ['schema' => [
            'type' => 'object', 'required' => ['transform'],
            'properties' => [
              'transform' => [
                'type' => 'object', 'required' => ['scale', 'rotationDegrees', 'translation'],
                'properties' => [
                  'scale' => ['type' => 'number', 'exclusiveMinimum' => 0],
                  'rotationDegrees' => ['type' => 'number'],
                  'translation' => ['type' => 'object', 'required' => ['x', 'y', 'z'],
                    'properties' => ['x' => ['type' => 'number'], 'y' => ['type' => 'number'], 'z' => ['type' => 'number']]],
                ],
              ],
              'bindings' => [
                'type' => ['array', 'null'], 'items' => ['type' => 'object', 'required' => ['nodeIndex', 'facilityId'],
                  'properties' => ['nodeIndex' => ['type' => 'integer', 'minimum' => 0], 'facilityId' => ['type' => 'string', 'format' => 'uuid']]],
              ],
              'removeBindingNodeIndices' => [
                'type' => 'array', 'uniqueItems' => true, 'items' => ['type' => 'integer', 'minimum' => 0],
                'description' => 'Explicitly remove stored node associations, including unavailable historical targets.',
              ],
            ],
          ]],
        ])),
        responses: [200 => new Response(description: 'Settings saved'), 403 => new Response(description: 'Missing permission'),
          404 => new Response(description: 'Model outside scope or missing'), 412 => new Response(description: 'Stale revision'),
          428 => new Response(description: 'If-Match required'), 422 => new Response(description: 'Invalid transformation or association')],
      ),
    ),
    new Post(
      name: FacilityModelOperations::ACTIVATE,
      uriTemplate: '/facility-models/{id}/activate',
      status: 200,
      read: false,
      deserialize: false,
      input: false,
      output: FacilityModelOutput::class,
      processor: FacilityModelProcessor::class,
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Activate a model and atomically deactivate the previous model',
        description: 'Requires If-Match: "revision-N".',
        parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'pattern' => '^"revision-[0-9]+"$'])],
        responses: [200 => new Response(description: 'Model activated'), 403 => new Response(description: 'Missing facilities write permission'),
          404 => new Response(description: 'Model outside organization scope or missing'), 412 => new Response(description: 'Stale revision'),
          428 => new Response(description: 'If-Match required'), 422 => new Response(description: 'Association outside building subtree')],
      ),
    ),
    new Delete(
      name: FacilityModelOperations::DELETE,
      uriTemplate: '/facility-models/{id}',
      status: 204,
      read: false,
      input: false,
      output: false,
      processor: FacilityModelProcessor::class,
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Delete a model and its immutable file',
        description: 'Requires If-Match: "revision-N".',
        parameters: [new Parameter(name: 'If-Match', in: 'header', required: true, schema: ['type' => 'string', 'pattern' => '^"revision-[0-9]+"$'])],
        responses: [204 => new Response(description: 'Model deleted'), 403 => new Response(description: 'Missing facilities write permission'),
          404 => new Response(description: 'Model outside organization scope or missing'), 412 => new Response(description: 'Stale revision'),
          428 => new Response(description: 'If-Match required')],
      ),
    ),
    // Raw-byte response follows the existing API Platform attachment download exception to DTO serialization.
    new Get(
      name: FacilityModelOperations::DOWNLOAD,
      uriTemplate: '/facility-models/{id}/download',
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      input: false,
      output: false,
      controller: DownloadFacilityModelController::class,
      openapi: new Operation(tags: ['Facility'], summary: 'Download the authenticated GLB file', responses: [
        200 => new Response(description: 'Immutable GLB file content', content: new ArrayObject([
          'model/gltf-binary' => ['schema' => ['type' => 'string', 'format' => 'binary']],
        ])), 403 => new Response(description: 'Missing facilities read permission'),
        404 => new Response(description: 'Model outside scope or missing'),
      ]),
    ),
  ],
)]
final class FacilityModelResource
{
}
