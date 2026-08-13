<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter};
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
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
      uriTemplate: '/interventions/{interventionId}/attachments',
      deserialize: false,
      input: false,
      output: InterventionAttachmentOutput::class,
      processor: InterventionMediaProcessor::class,
      status: Response::HTTP_CREATED,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(parameters: [
        new Parameter(name: 'interventionId', in: 'path', required: true, schema: ['type' => 'string']),
      ]),
    ),
    new GetCollection(
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
      uriTemplate: '/intervention-attachments/{id}',
      input: false,
      output: InterventionAttachmentOutput::class,
      provider: InterventionMediaProvider::class,
      security: "is_granted('ROLE_USER')",
    ),
    new Delete(
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
