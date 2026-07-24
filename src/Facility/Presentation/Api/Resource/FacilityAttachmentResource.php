<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Processor\Attachment\FacilityMediaProcessor;
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
        description: 'Uploads a multipart file attachment to a facility.',
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
  ],
)]
final class FacilityAttachmentResource
{
}
