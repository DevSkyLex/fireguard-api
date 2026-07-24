<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO MessageAttachmentOutput.
 *
 * Read representation of a file posted with a message. Also the row shape of
 * the conversation Files tab, so the two surfaces cannot drift apart.
 *
 * Deliberately exposes no `storagePath`: the storage key is an internal
 * addressing detail of the object store and must never reach a client. The
 * client downloads the bytes through `contentUrl` instead — an opaque,
 * access-controlled route, never the raw storage key.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageAttachmentOutput
{
  /**
   * Property id.
   *
   * @since 1.0.0
   */
  #[ApiProperty(identifier: true)]
  public string $id = '';

  /**
   * Property message.
   *
   * The owning message IRI.
   *
   * @since 1.0.0
   */
  public string $message = '';

  /**
   * Property conversation.
   *
   * The owning conversation IRI.
   *
   * @since 1.0.0
   */
  public string $conversation = '';

  /**
   * Property uploadedByMember.
   *
   * The uploading member's organization member IRI.
   *
   * @since 1.0.0
   */
  public string $uploadedByMember = '';

  /**
   * Property contentUrl.
   *
   * The download route serving the file's raw bytes
   * (`GET /api/messaging-attachments/{id}/content`). Access-controlled like
   * reading the owning conversation; lets the frontend link the attachment
   * instead of showing name/size metadata only.
   *
   * @since 1.0.0
   */
  public string $contentUrl = '';

  /**
   * Property fileName.
   *
   * @since 1.0.0
   */
  public string $fileName = '';

  /**
   * Property mimeType.
   *
   * @since 1.0.0
   */
  public string $mimeType = '';

  /**
   * Property size.
   *
   * Size in bytes.
   *
   * @since 1.0.0
   */
  public int $size = 0;

  /**
   * Property label.
   *
   * @since 1.0.0
   */
  public ?string $label = null;

  /**
   * Property revision.
   *
   * Optimistic-concurrency revision, echoed back for the delete precondition.
   *
   * @since 1.0.0
   */
  public int $revision = 1;

  /**
   * Property uploadedAt.
   *
   * @since 1.0.0
   */
  public string $uploadedAt = '';
}
