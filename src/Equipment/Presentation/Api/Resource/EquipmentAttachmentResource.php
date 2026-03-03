<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Dto\Input\Equipment\AddAttachmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Equipment\Presentation\Api\Processor\Equipment\{AddAttachmentProcessor, DeleteAttachmentProcessor};
use Equipment\Presentation\Api\Provider\Equipment\ListEquipmentAttachmentsProvider;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentAttachmentResource.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentAttachment',
  routePrefix: '/organizations',
  description: 'File attachments linked to equipment items.',
  operations: [
    new GetCollection(
      name: EquipmentOperations::LIST_EQUIPMENT_ATTACHMENTS,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/attachments',
      input: false,
      output: AttachmentOutput::class,
      provider: ListEquipmentAttachmentsProvider::class,
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'List attachments',
        description: 'Lists all attachments for a given equipment item.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachments retrieved'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Post(
      name: EquipmentOperations::ADD_ATTACHMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/attachments',
      input: AddAttachmentInput::class,
      output: AttachmentOutput::class,
      processor: AddAttachmentProcessor::class,
      denormalizationContext: ['groups' => [EquipmentSerializationGroup::WRITE]],
      normalizationContext: ['groups' => [EquipmentSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Add attachment',
        description: 'Uploads a file attachment to the equipment (base64-encoded content in JSON).',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Attachment uploaded'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment not found'),
        ],
      ),
    ),
    new Delete(
      name: EquipmentOperations::DELETE_ATTACHMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}',
      read: false,
      input: false,
      output: false,
      processor: DeleteAttachmentProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Delete attachment',
        description: 'Deletes an attachment from the equipment.',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Attachment deleted'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid identifier'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Equipment or attachment not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentAttachmentResource
{
}
