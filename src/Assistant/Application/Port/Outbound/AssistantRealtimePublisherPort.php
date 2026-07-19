<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

/**
 * Port AssistantRealtimePublisherPort.
 *
 * Assistant-owned real-time fan-out (Mercure), a THIRD, deliberately
 * separate topic scheme from Messaging's
 * (`/organizations/{organizationId}/conversations/{conversationId}`) and
 * Notification's (`/users/{userId}/notifications`): an already-issued
 * subscriber JWT for either of those topics must never be able to read an
 * assistant generation stream, so this port's implementation owns its own
 * `/organizations/{organizationId}/assistant/threads/{threadId}` topic and
 * its own JWT minting (`GetAssistantThreadSubscriptionProvider`). Implemented
 * by
 * `Assistant\Infrastructure\Adapter\Realtime\MercureAssistantRealtimePublisherAdapter`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantRealtimePublisherPort
{
  // #region Methods
  /**
   * Method publishGenerationEvent.
   *
   * Publishes a generation-progress event on the thread's private Mercure
   * topic. `$body` always carries the FULL accumulated reply text so far —
   * see {@see AssistantGenerationClientPort}'s docblock for why this is what
   * keeps a Messenger retry from ever producing duplicated visible content.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the assistant thread identifier
   * @param string $messageId the assistant message identifier
   * @param string $status the current {@see \Assistant\Domain\ValueObject\AssistantMessageStatus} value
   * @param string $body the full accumulated reply body so far
   * @param ?int $tokenCount the generated token count, once known
   * @param ?string $errorCode the failure code, once failed
   */
  public function publishGenerationEvent(
    string $organizationId,
    string $threadId,
    string $messageId,
    string $status,
    string $body,
    ?int $tokenCount = null,
    ?string $errorCode = null,
  ): void;
  // #endregion
}
