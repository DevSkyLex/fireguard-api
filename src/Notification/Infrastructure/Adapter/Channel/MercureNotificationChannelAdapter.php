<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Channel;

use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Outbound\MercureNotificationChannelPort;
use Notification\Domain\Model\Notification\Notification;
use Symfony\Component\Mercure\{HubInterface, Update};

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Adapter MercureNotificationChannelAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MercureNotificationChannelAdapter implements MercureNotificationChannelPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param HubInterface $hub the Mercure hub
   * @param string $topicPrefix the topic prefix
   */
  public function __construct(
    private HubInterface $hub,
    private string $topicPrefix = '/users',
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method publish.
   *
   * Publishes a notification to the Mercure hub.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification to publish
   * @param array<string, mixed> $channelPayload additional payload for the channel (not used in this implementation)
   *
   * @return void No return value
   */
  public function publish(Notification $notification, array $channelPayload = []): void
  {
    $userId = $notification->recipientUserId();

    if (null === $userId) {
      return;
    }

    // Must stay field-for-field identical to `NotificationOutput`: a client
    // merges a pushed notification into the same list the REST endpoint filled,
    // so a key missing here is a key missing on half the rows. `category` and
    // `organizationId` were absent, which broke per-category rendering and
    // organization scoping for live notifications only.
    $payload = [
      'id' => (string) $notification->id(),
      'type' => $notification->type(),
      'category' => NotificationType::category($notification->type()),
      'subject' => $notification->subject(),
      'body' => $notification->body(),
      'channels' => $notification->channels(),
      'payload' => $notification->payload(),
      'isRead' => $notification->isRead(),
      'readAt' => null !== $notification->readAt() ? $notification->readAt()->format('c') : null,
      'createdAt' => $notification->createdAt()->format('c'),
      'organizationId' => $notification->organizationId(),
    ];

    $update = new Update(
      topics: [$this->topicForUser($userId)],
      data: json_encode($payload, JSON_THROW_ON_ERROR),
      private: true,
    );

    $this->hub->publish($update);
  }

  /**
   * Method topicForUser.
   *
   * Generates the Mercure topic for a given user ID.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return string the mercure topic
   */
  private function topicForUser(string $userId): string
  {
    return sprintf('%s/%s/notifications', $this->topicPrefix, $userId);
  }
  // #endregion
}
