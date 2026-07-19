<?php

declare(strict_types=1);

namespace Messaging\Domain\Model\Attachment;

use DateTimeImmutable;
use Messaging\Domain\ValueObject\MessagingAttachmentId;

/**
 * Model MessagingAttachment.
 *
 * A file posted alongside a message. Also the row shown in the owning
 * conversation's Files tab — `conversationId` is carried directly on the
 * aggregate (denormalized in persistence too, see
 * `MessagingAttachmentRecord`) so listing a conversation's files never
 * requires joining through every message.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentId $id the attachment identifier
   * @param string $messageId the owning message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $uploadedByMemberId the uploading member's identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   */
  private function __construct(
    private MessagingAttachmentId $id,
    private string $messageId,
    private string $conversationId,
    private string $organizationId,
    private string $uploadedByMemberId,
    private string $fileName,
    private string $storagePath,
    private string $mimeType,
    private int $size,
    private DateTimeImmutable $uploadedAt,
    private ?string $label = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a newly uploaded messaging attachment.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentId $id the attachment identifier
   * @param string $messageId the owning message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $uploadedByMemberId the uploading member's identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param ?string $label the optional label
   *
   * @return self the created attachment
   */
  public static function create(
    MessagingAttachmentId $id,
    string $messageId,
    string $conversationId,
    string $organizationId,
    string $uploadedByMemberId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    ?string $label = null,
  ): self {
    return new self(
      id: $id,
      messageId: $messageId,
      conversationId: $conversationId,
      organizationId: $organizationId,
      uploadedByMemberId: $uploadedByMemberId,
      fileName: $fileName,
      storagePath: $storagePath,
      mimeType: $mimeType,
      size: $size,
      uploadedAt: new DateTimeImmutable(),
      label: $label,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an attachment from persisted state.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentId $id the attachment identifier
   * @param string $messageId the owning message identifier
   * @param string $conversationId the owning conversation identifier
   * @param string $organizationId the owning organization identifier
   * @param string $uploadedByMemberId the uploading member's identifier
   * @param string $fileName the original file name
   * @param string $storagePath the storage path
   * @param string $mimeType the MIME type
   * @param int $size the file size in bytes
   * @param DateTimeImmutable $uploadedAt the upload timestamp
   * @param ?string $label the optional label
   *
   * @return self the reconstituted attachment
   */
  public static function reconstitute(
    MessagingAttachmentId $id,
    string $messageId,
    string $conversationId,
    string $organizationId,
    string $uploadedByMemberId,
    string $fileName,
    string $storagePath,
    string $mimeType,
    int $size,
    DateTimeImmutable $uploadedAt,
    ?string $label = null,
  ): self {
    return new self($id, $messageId, $conversationId, $organizationId, $uploadedByMemberId, $fileName, $storagePath, $mimeType, $size, $uploadedAt, $label);
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): MessagingAttachmentId
  {
    return $this->id;
  }

  /**
   * Method messageId.
   *
   * @since 1.0.0
   */
  public function messageId(): string
  {
    return $this->messageId;
  }

  /**
   * Method conversationId.
   *
   * @since 1.0.0
   */
  public function conversationId(): string
  {
    return $this->conversationId;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): string
  {
    return $this->organizationId;
  }

  /**
   * Method uploadedByMemberId.
   *
   * @since 1.0.0
   */
  public function uploadedByMemberId(): string
  {
    return $this->uploadedByMemberId;
  }

  /**
   * Method fileName.
   *
   * @since 1.0.0
   */
  public function fileName(): string
  {
    return $this->fileName;
  }

  /**
   * Method storagePath.
   *
   * @since 1.0.0
   */
  public function storagePath(): string
  {
    return $this->storagePath;
  }

  /**
   * Method mimeType.
   *
   * @since 1.0.0
   */
  public function mimeType(): string
  {
    return $this->mimeType;
  }

  /**
   * Method size.
   *
   * @since 1.0.0
   */
  public function size(): int
  {
    return $this->size;
  }

  /**
   * Method label.
   *
   * @since 1.0.0
   */
  public function label(): ?string
  {
    return $this->label;
  }

  /**
   * Method uploadedAt.
   *
   * @since 1.0.0
   */
  public function uploadedAt(): DateTimeImmutable
  {
    return $this->uploadedAt;
  }
  // #endregion
}
