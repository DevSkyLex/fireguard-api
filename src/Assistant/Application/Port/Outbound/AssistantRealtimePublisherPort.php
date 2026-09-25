<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

use Assistant\Domain\Model\Message\AssistantMessage;

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
   * @param AssistantMessage $message the persisted generation state to publish
   */
  public function publishGenerationEvent(AssistantMessage $message): void;
  // #endregion
}
