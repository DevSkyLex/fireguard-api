<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Parameter, Response};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Facility\Presentation\Api\Processor\Attachment\{FacilityMediaProcessor, SetPrimaryFacilityAttachmentProcessor};
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use Facility\Presentation\Api\Serialization\FacilitySerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource FacilityAttachmentResource.
 *
 * Generalized multipart file attachments on a facility, mirroring
 * `Equipment\Presentation\Api\Resource\MediaResource`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityAttachment',
  description: 'File attachments linked to a facility.',
  operations: [
    new Post(
      uriTemplate: '/facilities/{facilityId}/attachments',
      deserialize: false,
      input: false,
      output: FacilityAttachmentOutput::class,
      processor: FacilityMediaProcessor::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Upload a facility attachment',
        description: 'Uploads a multipart file attachment to a facility. An optional `kind` field ("document", the default, or "floor_plan") selects the attachment kind; a floor plan is restricted to image/png, image/jpeg, image/webp or image/svg+xml and has its pixel dimensions probed server-side.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Attachment uploaded'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'MIME type or size rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new GetCollection(
      uriTemplate: '/facilities/{facilityId}/attachments',
      input: false,
      output: FacilityAttachmentOutput::class,
      provider: FacilityMediaProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'List facility attachments',
        description: 'Lists all attachments for a given facility.',
        parameters: [
          new Parameter(name: 'kind', in: 'query', required: false, description: 'Filter by attachment kind ("document" or "floor_plan").', schema: ['type' => 'string', 'enum' => ['document', 'floor_plan']]),
        ],
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachments retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Facility not found'),
        ],
      ),
    ),
    new Get(
      uriTemplate: '/facility-attachments/{id}',
      input: false,
      output: FacilityAttachmentOutput::class,
      provider: FacilityMediaProvider::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Get a facility attachment',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment not found'),
        ],
      ),
    ),
    new Delete(
      uriTemplate: '/facility-attachments/{id}',
      read: false,
      input: false,
      output: false,
      processor: FacilityMediaProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Delete a facility attachment',
        responses: [
          HttpResponse::HTTP_NO_CONTENT => new Response(description: 'Attachment deleted'),
          HttpResponse::HTTP_PRECONDITION_REQUIRED => new Response(description: 'If-Match header is required'),
          HttpResponse::HTTP_PRECONDITION_FAILED => new Response(description: 'Attachment revision is stale'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment not found'),
        ],
      ),
    ),
    new Post(
      name: FacilityOperations::SET_PRIMARY_FACILITY_ATTACHMENT,
      uriTemplate: '/facility-attachments/{id}/primary',
      status: HttpResponse::HTTP_OK,
      read: false,
      input: false,
      output: FacilityAttachmentOutput::class,
      processor: SetPrimaryFacilityAttachmentProcessor::class,
      normalizationContext: ['groups' => [FacilitySerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Set the facility primary floor plan',
        description: 'Promotes a `floor_plan` attachment to be the facility\'s primary plan, atomically clearing whichever attachment carried the flag before. A `document` attachment is refused with 409 — a POST verb-action route, mirroring `/facilities/{id}/archive` and `/facilities/{id}/move`.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment promoted to primary plan'),
          HttpResponse::HTTP_CONFLICT => new Response(description: 'The attachment is not a floor plan'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment not found'),
        ],
      ),
    ),
  ],
)]
final class FacilityAttachmentResource
{
}
