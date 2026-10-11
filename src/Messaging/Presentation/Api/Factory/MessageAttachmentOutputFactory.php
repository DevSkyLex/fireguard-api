<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Attachment\MessagingAttachmentView;
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\AddMessageAttachmentResult;
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;

/**
 * Factory MessageAttachmentOutputFactory.
 *
 * @category Factory
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageAttachmentOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachmentView $attachment the attachment metadata
   *
   * @return MessageAttachmentOutput the attachment output
   */
  public function fromView(MessagingAttachmentView $attachment): MessageAttachmentOutput
  {
    $output = new MessageAttachmentOutput();
    $output->id = $attachment->id;
    $output->message = '/api/messages/' . $attachment->messageId;
    $output->conversation = '/api/conversations/' . $attachment->conversationId;
    $output->uploadedByMember = '/api/organizations/' . $attachment->organizationId . '/members/' . $attachment->uploadedByMemberId;
    $output->contentUrl = '/api/messaging-attachments/' . $attachment->id . '/content';
    $output->fileName = $attachment->fileName;
    $output->mimeType = $attachment->mimeType;
    $output->size = $attachment->size;
    $output->label = $attachment->label;
    $output->revision = $attachment->revision;
    $output->uploadedAt = $attachment->uploadedAt->format('c');

    return $output;
  }

  /**
   * Method fromUploadResult
   *
   * Translates the committed upload result without querying persistence again.
   *
   * @access public
   *
   * @param AddMessageAttachmentResult $result committed attachment metadata
   *
   * @return MessageAttachmentOutput attachment representation
   */
  public function fromUploadResult(AddMessageAttachmentResult $result): MessageAttachmentOutput
  {
    return $this->fromView(new MessagingAttachmentView(
      $result->attachmentId,
      $result->messageId,
      $result->conversationId,
      $result->organizationId,
      $result->uploadedByMemberId,
      $result->fileName,
      $result->mimeType,
      $result->size,
      $result->label,
      $result->uploadedAt,
      $result->revision,
    ));
  }
  // #endregion
}
