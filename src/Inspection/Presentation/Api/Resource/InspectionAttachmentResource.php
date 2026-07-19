<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Delete, Get, GetCollection, Post};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use Inspection\Presentation\Api\Processor\Attachment\InspectionMediaProcessor;
use Inspection\Presentation\Api\Provider\Attachment\InspectionMediaProvider;
use Inspection\Presentation\Api\Serialization\InspectionSerializationGroup;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InspectionAttachmentResource.
 *
 * Generalized multipart file attachments on an inspection, and — via the
 * `/non-conformities/{nonConformityId}/attachments` sub-collection — the
 * field-proof photos of a non-conformity. Both share the same
 * `inspection_attachments` table (see `src/Inspection/MODULE.md`).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InspectionAttachment',
  description: 'File attachments linked to an inspection or a non-conformity.',
  operations: [
    new Post(
      name: 'add_inspection_attachment',
      uriTemplate: '/inspections/{inspectionId}/attachments',
      deserialize: false,
      input: false,
      output: InspectionAttachmentOutput::class,
      processor: InspectionMediaProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Upload an inspection attachment',
        description: 'Uploads a multipart file attachment to an inspection.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Attachment uploaded'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'MIME type or size rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
        ],
      ),
    ),
    new GetCollection(
      name: 'list_inspection_attachments',
      uriTemplate: '/inspections/{inspectionId}/attachments',
      input: false,
      output: InspectionAttachmentOutput::class,
      provider: InspectionMediaProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List inspection attachments',
        description: 'Lists the inspection-level attachments of an inspection.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachments retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Inspection not found'),
        ],
      ),
    ),
    new Post(
      name: 'add_non_conformity_attachment',
      uriTemplate: '/non-conformities/{nonConformityId}/attachments',
      deserialize: false,
      input: false,
      output: InspectionAttachmentOutput::class,
      processor: InspectionMediaProcessor::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Upload a non-conformity field-proof photo',
        description: 'Uploads a multipart file attachment (field-proof photo) to a non-conformity.',
        responses: [
          HttpResponse::HTTP_CREATED => new Response(description: 'Attachment uploaded'),
          HttpResponse::HTTP_BAD_REQUEST => new Response(description: 'Invalid input'),
          HttpResponse::HTTP_UNPROCESSABLE_ENTITY => new Response(description: 'MIME type or size rejected'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Non-conformity not found'),
        ],
      ),
    ),
    new GetCollection(
      name: 'list_non_conformity_attachments',
      uriTemplate: '/non-conformities/{nonConformityId}/attachments',
      input: false,
      output: InspectionAttachmentOutput::class,
      provider: InspectionMediaProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'List non-conformity field-proof photos',
        description: 'Lists the field-proof photo attachments of a non-conformity.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachments retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Non-conformity not found'),
        ],
      ),
    ),
    new Get(
      name: 'get_inspection_attachment',
      uriTemplate: '/inspection-attachments/{id}',
      input: false,
      output: InspectionAttachmentOutput::class,
      provider: InspectionMediaProvider::class,
      normalizationContext: ['groups' => [InspectionSerializationGroup::READ]],
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Get an inspection attachment',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment retrieved'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment not found'),
        ],
      ),
    ),
    new Delete(
      name: 'delete_inspection_attachment',
      uriTemplate: '/inspection-attachments/{id}',
      read: false,
      input: false,
      output: false,
      processor: InspectionMediaProcessor::class,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Inspection'],
        summary: 'Delete an inspection attachment',
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
final class InspectionAttachmentResource
{
}
