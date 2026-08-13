<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Intervention\Presentation\Api\Controller\DownloadInterventionAttachmentController;
use Intervention\Presentation\Api\Operation\InterventionOperations;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource InterventionAttachmentContentResource.
 *
 * The download endpoint for an intervention attachment's raw bytes
 * (`GET /intervention-attachments/{id}/download`). Kept on its OWN resource
 * rather than as an operation on `InterventionAttachmentResource` because it
 * returns a binary `Response`, not a serialized `InterventionAttachmentOutput`:
 * `read`, `write`, `deserialize`, `serialize` and `output` are disabled so
 * API Platform's state pipeline steps aside and the controller's `Response`
 * is returned as-is — mirrors `Messaging\...\MessagingAttachmentContentResource`.
 * Access is gated entirely inside `GetInterventionAttachmentContentHandler`
 * (the flat `organization.interventions.read` permission, deliberately
 * without the phase-based write restriction the upload/delete endpoints
 * apply — a published intervention's evidence must stay downloadable).
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'InterventionAttachmentContent',
  description: 'Binary download of an intervention attachment.',
  operations: [
    new Get(
      name: InterventionOperations::DOWNLOAD_INTERVENTION_ATTACHMENT,
      uriTemplate: '/intervention-attachments/{id}/download',
      controller: DownloadInterventionAttachmentController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Intervention'],
        summary: 'Download an intervention attachment',
        description: 'Streams the stored file bytes with Content-Disposition: attachment. '
          . 'Gated by the flat organization.interventions.read permission — no phase restriction, '
          . 'so a published intervention\'s evidence stays downloadable.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment file content'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions (requires organization.interventions.read)'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment (or its stored content) not found'),
        ],
      ),
    ),
  ],
)]
final class InterventionAttachmentContentResource
{
}
