<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Realtime;

use Assistant\Application\Port\Outbound\AssistantRealtimePublisherPort;
use Symfony\Component\Mercure\{HubInterface, Update};

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter MercureAssistantRealtimePublisherAdapter.
 *
 * An Assistant-owned Mercure publisher, mirroring
 * `Messaging\Infrastructure\Adapter\Realtime\MercureMessagingRealtimePublisherAdapter`'s
 * use of the Mercure hub, but with its OWN, THIRD topic scheme
 * (`/organizations/{organizationId}/assistant/threads/{threadId}`) —
 * deliberately never reusing the notification (`/users/{userId}/notifications`)
 * or conversation (`/organizations/{organizationId}/conversations/{conversationId}`)
 * schemes: an already-issued subscriber JWT for either of those must never
 * be able to read an assistant generation stream.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MercureAssistantRealtimePublisherAdapter implements AssistantRealtimePublisherPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param HubInterface $hub the Mercure hub
   */
  public function __construct(
    private HubInterface $hub,
  ) {
  }
  // #endregion

  // #region Methods
  public function publishGenerationEvent(
    string $organizationId,
    string $threadId,
    string $messageId,
    string $status,
    string $body,
    ?int $tokenCount = null,
    ?string $errorCode = null,
  ): void {
    $update = new Update(
      topics: [self::topic($organizationId, $threadId)],
      data: json_encode([
        'messageId' => $messageId,
        'status' => $status,
        'body' => $body,
        'tokenCount' => $tokenCount,
        'errorCode' => $errorCode,
      ], JSON_THROW_ON_ERROR),
      private: true,
    );

    $this->hub->publish($update);
  }

  /**
   * Method topic.
   *
   * @static
   *
   * Builds the private per-thread Mercure topic. Deliberately never a
   * wildcard so a subscriber JWT scoped to one thread can never read
   * another's generation stream.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the assistant thread identifier
   *
   * @return string the Mercure topic
   */
  public static function topic(string $organizationId, string $threadId): string
  {
    return sprintf('/organizations/%s/assistant/threads/%s', $organizationId, $threadId);
  }
  // #endregion
}
