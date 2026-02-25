<?php

declare(strict_types=1);

namespace Notification\Application\UseCase\Command\Notification\SendNotification;

use InvalidArgumentException;
use Notification\Application\Contract\Notification\NotificationChannel;
use Notification\Application\Port\Outbound\{EmailNotificationChannelPort, MercureNotificationChannelPort, NotificationRepositoryPort};
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Email;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

/**
 * UseCase SendNotificationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SendNotificationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NotificationRepositoryPort $notificationRepository the notification repository port
   * @param EmailNotificationChannelPort $emailChannel the email channel port
   * @param MercureNotificationChannelPort $mercureChannel the mercure channel port
   * @param LoggerPort $logger the logger port
   * @param UuidFactory $uuidFactory the UUID factory
   */
  public function __construct(
    private NotificationRepositoryPort $notificationRepository,
    private EmailNotificationChannelPort $emailChannel,
    private MercureNotificationChannelPort $mercureChannel,
    private LoggerPort $logger,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Sends and stores a notification.
   *
   * @since 1.0.0
   *
   * @param SendNotificationCommand $command the command payload
   *
   * @return SendNotificationResult the use case result
   */
  public function __invoke(SendNotificationCommand $command): SendNotificationResult
  {
    $channels = $this->normalizeChannels($command->channels);
    $recipientUserId = $this->normalizeNullableString($command->recipientUserId);
    $recipientEmail = $this->normalizeNullableString($command->recipientEmail);

    if (null === $recipientUserId && null === $recipientEmail) {
      throw new InvalidArgumentException('At least one recipient target (userId or email) is required.');
    }

    $type = trim($command->type);
    if ('' === $type) {
      throw new InvalidArgumentException('Notification type is required.');
    }

    $subject = trim($command->subject);
    if ('' === $subject) {
      throw new InvalidArgumentException('Notification subject is required.');
    }

    if ($this->containsChannel($channels, NotificationChannel::EMAIL) && null === $recipientEmail) {
      throw new InvalidArgumentException('Recipient email is required for email notifications.');
    }

    if ($this->containsChannel($channels, NotificationChannel::MERCURE) && null === $recipientUserId) {
      throw new InvalidArgumentException('Recipient userId is required for Mercure notifications.');
    }

    $email = null;
    if (null !== $recipientEmail) {
      $email = $this->normalizeEmail($recipientEmail);
    }

    /** @var NotificationId $notificationId */
    $notificationId = $this->uuidFactory->create(NotificationId::class);
    $channelValues = array_map(
      static fn (NotificationChannel $channel): string => $channel->value,
      $channels,
    );

    $notification = Notification::create(
      id: $notificationId,
      type: $type,
      subject: $subject,
      body: $command->body,
      channels: $channelValues,
      payload: $command->payload,
      recipientUserId: $recipientUserId,
      recipientEmail: $email,
    );

    $this->notificationRepository->save($notification);

    $channelDelivery = [];
    foreach ($channels as $channel) {
      $channelPayload = $this->extractChannelPayload($command->deliveryPayload, $channel->value);

      try {
        match ($channel) {
          NotificationChannel::EMAIL => $this->emailChannel->send($notification, $channelPayload),
          NotificationChannel::MERCURE => $this->mercureChannel->publish($notification, $channelPayload),
        };

        $channelDelivery[$channel->value] = true;
      } catch (Throwable $exception) {
        $channelDelivery[$channel->value] = false;

        $this->logger->warning('Notification channel delivery failed.', [
          'notificationId' => (string) $notification->id(),
          'channel' => $channel->value,
          'type' => $notification->type(),
          'recipientUserId' => $notification->recipientUserId(),
          'recipientEmail' => null !== $notification->recipientEmail() ? (string) $notification->recipientEmail() : null,
          'error' => $exception->getMessage(),
          'cause' => $exception->getPrevious()?->getMessage(),
        ]);

        // Best-effort delivery: notification creation must not fail on channel errors.
      }
    }

    return new SendNotificationResult(
      id: (string) $notification->id(),
      type: $notification->type(),
      subject: $notification->subject(),
      body: $notification->body(),
      channels: $notification->channels(),
      payload: $notification->payload(),
      channelDelivery: $channelDelivery,
      createdAt: $notification->createdAt(),
      recipientUserId: $notification->recipientUserId(),
      recipientEmail: null !== $notification->recipientEmail() ? (string) $notification->recipientEmail() : null,
    );
  }

  /**
   * Method normalizeChannels.
   *
   * @since 1.0.0
   *
   * @param array<int, NotificationChannel> $channels the raw channels
   *
   * @return list<NotificationChannel> the normalized channels
   */
  private function normalizeChannels(array $channels): array
  {
    if ([] === $channels) {
      throw new InvalidArgumentException('At least one notification channel is required.');
    }

    $normalized = [];
    foreach ($channels as $channel) {
      if (!array_key_exists($channel->value, $normalized)) {
        $normalized[$channel->value] = $channel;
      }
    }

    return array_values($normalized);
  }

  /**
   * Method normalizeEmail.
   *
   * @since 1.0.0
   *
   * @param string $email the raw email
   *
   * @return Email the email value object
   */
  private function normalizeEmail(string $email): Email
  {
    try {
      return new Email(strtolower(trim($email)));
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException('Invalid email address provided for notification.', 0, $exception);
    }
  }

  /**
   * Method normalizeNullableString.
   *
   * @since 1.0.0
   *
   * @param string|null $value the raw value
   *
   * @return string|null the normalized value
   */
  private function normalizeNullableString(?string $value): ?string
  {
    if (null === $value) {
      return null;
    }

    $normalized = trim($value);

    return '' === $normalized ? null : $normalized;
  }

  /**
   * Method containsChannel.
   *
   * @since 1.0.0
   *
   * @param list<NotificationChannel> $channels the channel list
   * @param NotificationChannel $needle the channel to find
   *
   * @return bool true when channel exists
   */
  private function containsChannel(array $channels, NotificationChannel $needle): bool
  {
    return in_array($needle, $channels, true);
  }

  /**
   * Method extractChannelPayload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $deliveryPayload delivery payload map
   * @param string $channelKey the channel key
   *
   * @return array<string, mixed> the channel payload
   */
  private function extractChannelPayload(array $deliveryPayload, string $channelKey): array
  {
    $payload = $deliveryPayload[$channelKey] ?? null;

    if (!is_array($payload)) {
      return [];
    }

    $normalized = [];
    foreach ($payload as $key => $value) {
      if (!is_string($key)) {
        continue;
      }

      $normalized[$key] = $value;
    }

    return $normalized;
  }

  // #endregion
}
