<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Domain\Model\Message\AssistantMessage;

/**
 * Service AssistantMessageViewMapper.
 *
 * Builds the public read projection from the owning aggregate.
 *
 * @category Service
 */
final class AssistantMessageViewMapper
{
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantMessage $message the assistant message aggregate
   *
   * @return AssistantMessageView the view built from the aggregate
   */
  public static function fromDomain(AssistantMessage $message): AssistantMessageView
  {
    return new AssistantMessageView(
      id: (string) $message->id(),
      threadId: $message->threadId(),
      organizationId: $message->organizationId(),
      role: $message->role()->value,
      body: $message->body(),
      status: $message->status()->value,
      errorCode: $message->errorCode(),
      tokenCount: $message->tokenCount(),
      createdAt: $message->createdAt(),
      completedAt: $message->completedAt(),
      attemptId: $message->attemptId() ?? ($message->canCancel() ? (string) $message->id() : null),
      attemptNumber: $message->attemptNumber(),
      attemptSequence: $message->attemptSequence(),
      attemptExpiresAt: $message->attemptExpiresAt(),
      canCancel: $message->canCancel(),
      canRetry: $message->canRetry(),
    );
  }
}
