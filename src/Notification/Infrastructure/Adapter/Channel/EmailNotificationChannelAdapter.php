<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Channel;

use Notification\Application\Port\Outbound\EmailNotificationChannelPort;
use Notification\Domain\Model\Notification\Notification;
use Shared\Application\Port\Outbound\MailerPort;

use function is_string;

/**
 * Adapter EmailNotificationChannelAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailNotificationChannelAdapter implements EmailNotificationChannelPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MailerPort $mailer the mailer port
   */
  public function __construct(
    private MailerPort $mailer,
  ) {
  }
  // #endregion

  // #region Methods
  public function send(Notification $notification, array $channelPayload = []): void
  {
    $recipient = $notification->recipientEmail();

    if (null === $recipient) {
      return;
    }

    $body = $notification->body();
    $channelBody = $channelPayload['body'] ?? null;
    if (is_string($channelBody) && '' !== $channelBody) {
      $body = $channelBody;
    }

    $this->mailer->send(
      to: [(string) $recipient],
      subject: $notification->subject(),
      body: $body,
    );
  }
  // #endregion
}
