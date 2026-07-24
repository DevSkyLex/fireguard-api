<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;

/**
 * Factory MessageAttachmentOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageAttachmentOutputFactory
{
  // #region Methods
  /**
   * Method fromAttachment.
   *
   * @since 1.0.0
   *
   * @param MessagingAttachment $attachment the attachment aggregate
   *
   * @return MessageAttachmentOutput the attachment output
   */
  public function fromAttachment(MessagingAttachment $attachment): MessageAttachmentOutput
  {
    $output = new MessageAttachmentOutput();
    $output->id = (string) $attachment->id();
    $output->message = '/api/messages/' . $attachment->messageId();
    $output->conversation = '/api/conversations/' . $attachment->conversationId();
    $output->uploadedByMember = '/api/organizations/' . $attachment->organizationId() . '/members/' . $attachment->uploadedByMemberId();
    $output->contentUrl = '/api/messaging-attachments/' . (string) $attachment->id() . '/content';
    $output->fileName = $attachment->fileName();
    $output->mimeType = $attachment->mimeType();
    $output->size = $attachment->size();
    $output->label = $attachment->label();
    $output->uploadedAt = $attachment->uploadedAt()->format('c');

    return $output;
  }
  // #endregion
}
