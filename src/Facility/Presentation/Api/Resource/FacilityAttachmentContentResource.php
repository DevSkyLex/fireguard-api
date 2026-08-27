<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Facility\Presentation\Api\Controller\DownloadFacilityAttachmentController;
use Facility\Presentation\Api\Operation\FacilityOperations;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource FacilityAttachmentContentResource.
 *
 * The download endpoint for a facility attachment's raw bytes
 * (`GET /facility-attachments/{id}/download`). Kept on its OWN resource
 * rather than as an operation on `FacilityAttachmentResource` because it
 * returns a binary `Response`, not a serialized `FacilityAttachmentOutput`:
 * `read`, `write`, `deserialize`, `serialize` and `output` are disabled so
 * API Platform's state pipeline steps aside and the controller's `Response`
 * is returned as-is — mirrors `Intervention\...\InterventionAttachmentContentResource`
 * and `Messaging\...\MessagingAttachmentContentResource`. Access is gated
 * entirely inside `DownloadFacilityAttachmentController`, reusing the same
 * `organization.facilities.read` permission as every other facility
 * attachment read surface.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'FacilityAttachmentContent',
  description: 'Binary download of a facility attachment.',
  operations: [
    new Get(
      name: FacilityOperations::DOWNLOAD_FACILITY_ATTACHMENT,
      uriTemplate: '/facility-attachments/{id}/download',
      controller: DownloadFacilityAttachmentController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Facility'],
        summary: 'Download a facility attachment',
        description: 'Streams the stored file bytes with Content-Disposition: attachment and '
          . 'X-Content-Type-Options: nosniff. A floor_plan attachment may be an SVG — the shared '
          . 'AttachmentDownloadResponder never serves it inline, so a browser downloads rather than '
          . 'executes it. Gated by organization.facilities.read.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment file content'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires organization.facilities.read)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment (or its stored content) not found'),
        ],
      ),
    ),
  ],
)]
final class FacilityAttachmentContentResource
{
}
