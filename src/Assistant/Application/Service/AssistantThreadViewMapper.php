<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Domain\Model\Thread\AssistantThread;

/**
 * Service AssistantThreadViewMapper.
 *
 * Builds the public read projection from the owning aggregate.
 *
 * @category Service
 */
final class AssistantThreadViewMapper
{
  /**
   * Method fromDomain.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param AssistantThread $thread the assistant thread aggregate
   *
   * @return AssistantThreadView the view built from the aggregate
   */
  public static function fromDomain(AssistantThread $thread): AssistantThreadView
  {
    return new AssistantThreadView(
      id: (string) $thread->id(),
      organizationId: $thread->organizationId(),
      memberId: $thread->memberId(),
      title: $thread->title(),
      model: $thread->model(),
      createdAt: $thread->createdAt(),
      updatedAt: $thread->updatedAt(),
      lastMessageAt: $thread->lastMessageAt(),
    );
  }
}
