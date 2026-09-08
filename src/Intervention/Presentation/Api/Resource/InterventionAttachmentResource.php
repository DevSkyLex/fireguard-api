<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, RequestBody};
use ArrayObject;
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Intervention\Presentation\Api\Processor\Attachment\InterventionMediaProcessor;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource InterventionAttachmentResource.
 *
 * Generalized multipart file attachments on an intervention. Write access
 * is phase-based (see `InterventionMediaProcessor`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionAttachment',
  operations: [
    new Post(
      name: InterventionOperations::CREATE_INTERVENTION_ATTACHMENT,
      uriTemplate: '/interventions/{interventionId}/attachments',
      deserialize: false,
      input: false,
      output: InterventionAttachmentOutput::class,
      processor: InterventionMediaProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        parameters: [
          new Parameter(name: 'interventionId', in: 'path', required: true, schema: ['type' => 'string']),
        ],
        requestBody: new RequestBody(
          content: new ArrayObject([
            'multipart/form-data' => [
              'schema' => [
                'type' => 'object',
                'properties' => [
                  'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'The attachment file content.'],
                  'label' => ['type' => 'string', 'description' => 'Optional display label.'],
                  'workItemId' => ['type' => 'string', 'description' => 'Optional owning work item IRI or bare id — scopes the attachment to that work item.'],
                  'kind' => ['type' => 'string', 'enum' => ['file', 'signature'], 'description' => 'Attachment kind. Defaults to "file"; "signature" is the typed completion signature (at most one per intervention, image MIME only).'],
                  'clientId' => ['type' => 'string', 'format' => 'uuid', 'description' => 'Optional client-generated attachment UUID (idempotency key for offline replay). Replaying the same clientId returns the already-stored attachment instead of creating a duplicate; a clientId owned by another intervention is rejected with 409.'],
                ],
                'required' => ['file'],
              ],
            ],
          ]),
        ),
      ),
    ),
    new GetCollection(
      name: InterventionOperations::LIST_INTERVENTION_ATTACHMENTS,
      uriTemplate: '/interventions/{interventionId}/attachments',
      input: false,
      output: InterventionAttachmentOutput::class,
      provider: InterventionMediaProvider::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'interventionId', in: 'path', required: true, schema: ['type' => 'string']),
        new Parameter(name: 'workItem', in: 'query', required: false, schema: ['type' => 'string'], description: 'Narrows the list to attachments scoped to this work item (IRI or bare id).'),
      ]),
    ),
    new Get(
      name: InterventionOperations::GET_INTERVENTION_ATTACHMENT,
      uriTemplate: '/intervention-attachments/{id}',
      input: false,
      output: InterventionAttachmentOutput::class,
      provider: InterventionMediaProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
      name: InterventionOperations::DELETE_INTERVENTION_ATTACHMENT,
      uriTemplate: '/intervention-attachments/{id}',
      read: false,
      input: false,
      output: false,
      processor: InterventionMediaProcessor::class,
      status: Response::HTTP_NO_CONTENT,
      security: "is_granted('ROLE_USER')",
    ),
  ],
)]
final class InterventionAttachmentResource
{
}
