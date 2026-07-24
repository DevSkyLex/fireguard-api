<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Resource;

use ApiPlatform\Metadata\{ApiResource, Get};
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Messaging\Presentation\Api\Controller\DownloadMessagingAttachmentController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Resource MessagingAttachmentContentResource.
 *
 * The download endpoint for a message attachment's raw bytes
 * (`GET /messaging-attachments/{id}/content`). Kept on its OWN resource
 * rather than as an operation on `MessagingAttachmentResource` because it
 * returns a binary `Response`, not a serialized `MessageAttachmentOutput`:
 * `read`, `write`, `deserialize`, `serialize` and `output` are disabled so
 * API Platform's state pipeline steps aside and the controller's `Response`
 * is returned as-is — the same mechanism (and separation) used by
 * `Compliance\...\SafetyRegisterExportResource`. Access is gated exactly like
 * reading the owning conversation, entirely inside
 * `DownloadMessageAttachmentHandler`.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'MessagingAttachmentContent',
  description: 'Binary download of a message attachment.',
  operations: [
    new Get(
      name: 'download_message_attachment',
      uriTemplate: '/messaging-attachments/{id}/content',
      controller: DownloadMessagingAttachmentController::class,
      read: false,
      write: false,
      deserialize: false,
      serialize: false,
      output: false,
      security: "is_granted('ROLE_USER')",
      openapi: new Operation(
        tags: ['Messaging'],
        summary: 'Download a message attachment',
        description: 'Streams the stored file bytes with Content-Disposition: attachment. '
          . 'Gated by the same access rule as reading the owning conversation.',
        responses: [
          HttpResponse::HTTP_OK => new Response(description: 'Attachment file content'),
          HttpResponse::HTTP_FORBIDDEN => new Response(description: 'Insufficient permissions'),
          HttpResponse::HTTP_NOT_FOUND => new Response(description: 'Attachment (or its stored content) not found'),
        ],
      ),
    ),
  ],
)]
final class MessagingAttachmentContentResource
{
}
