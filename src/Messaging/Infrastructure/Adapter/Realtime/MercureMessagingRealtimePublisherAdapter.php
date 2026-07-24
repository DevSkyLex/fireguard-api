<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Realtime;

use Messaging\Application\Port\Outbound\MessagingRealtimePublisherPort;
use Symfony\Component\Mercure\{HubInterface, Update};

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter MercureMessagingRealtimePublisherAdapter.
 *
 * A Messaging-owned Mercure publisher, mirroring
 * `Notification\Infrastructure\Adapter\Channel\MercureNotificationChannelAdapter`'s
 * use of the Mercure hub, but with its OWN topic scheme
 * (`/organizations/{organizationId}/conversations/{conversationId}`, no
 * `/users` prefix) and no involvement of `NotificationPort`: a posted
 * message is a live update on the conversation's own private topic, not a
 * persisted per-recipient notification.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MercureMessagingRealtimePublisherAdapter implements MessagingRealtimePublisherPort
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
  public function publishMessage(string $organizationId, string $conversationId, array $payload): void
  {
    $update = new Update(
      topics: [self::topic($organizationId, $conversationId)],
      data: json_encode($payload, JSON_THROW_ON_ERROR),
      private: true,
    );

    $this->hub->publish($update);
  }

  /**
   * Method topic.
   *
   * @static
   *
   * Builds the private per-conversation Mercure topic. Deliberately never a
   * wildcard so a subscriber JWT scoped to one conversation can never read
   * another's updates — this is re-tested when v2 introduces
   * `visibility: participants`.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $conversationId the conversation identifier
   *
   * @return string the Mercure topic
   */
  public static function topic(string $organizationId, string $conversationId): string
  {
    return sprintf('/organizations/%s/conversations/%s', $organizationId, $conversationId);
  }
  // #endregion
}
