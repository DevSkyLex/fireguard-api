<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Equipment\Presentation\Api\Controller\DownloadEquipmentAttachmentController;
use Equipment\Presentation\Api\Operation\EquipmentOperations;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource EquipmentAttachmentContentResource.
 *
 * The download endpoint for an equipment attachment's raw bytes
 * (`GET /organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download`).
 * Kept on its OWN resource rather than as an operation on
 * `EquipmentAttachmentResource` because it returns a binary `Response`, not a
 * serialized `AttachmentOutput`: `read`, `write`, `deserialize`, `serialize`
 * and `output` are disabled so API Platform's state pipeline steps aside and
 * the controller's `Response` is returned as-is — mirrors
 * `Facility\...\FacilityAttachmentContentResource` and
 * `Intervention\...\InterventionAttachmentContentResource`. Access is gated
 * by `organization.equipment.read` in `DownloadEquipmentAttachmentController`,
 * the same permission `ListEquipmentAttachmentsProvider` enforces.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'EquipmentAttachmentContent',
  routePrefix: '/organizations',
  description: 'Binary download of an equipment attachment.',
  operations: [
    new Get(
      name: EquipmentOperations::DOWNLOAD_ATTACHMENT,
      uriTemplate: '/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download',
      controller: DownloadEquipmentAttachmentController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Equipment'],
        summary: 'Download an equipment attachment',
        description: 'Streams the stored file bytes with Content-Disposition: attachment and '
          . 'X-Content-Type-Options: nosniff. Gated by organization.equipment.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment file content'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires organization.equipment.read)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Organization, equipment, attachment, or its stored content not found'),
        ],
      ),
    ),
  ],
)]
final class EquipmentAttachmentContentResource
{
}
