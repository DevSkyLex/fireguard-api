<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Channel;

use Notification\Application\Port\Outbound\EmailNotificationChannelPort;
use Notification\Domain\Model\Notification\Notification;
use Shared\Application\Port\Outbound\MailerPort;
use Twig\Environment;

use function array_merge;
use function is_array;
use function is_string;
use function trim;

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
  private const string DEFAULT_TEMPLATE = 'notification/email/default.html.twig';

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MailerPort $mailer the mailer port
   * @param Environment $twig the Twig renderer
   */
  public function __construct(
    private MailerPort $mailer,
    private Environment $twig,
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

    $body = $this->twig->render(
      $this->resolveTemplate($channelPayload),
      $this->buildContext($notification, $channelPayload),
    );

    $this->mailer->send(
      to: [(string) $recipient],
      subject: $notification->subject(),
      body: $body,
    );
  }

  /**
   * Method resolveTemplate.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $channelPayload channel-specific payload
   *
   * @return string the template to render
   */
  private function resolveTemplate(array $channelPayload): string
  {
    $template = $channelPayload['template'] ?? null;
    if (!is_string($template)) {
      return self::DEFAULT_TEMPLATE;
    }

    $normalizedTemplate = trim($template);

    return '' === $normalizedTemplate ? self::DEFAULT_TEMPLATE : $normalizedTemplate;
  }

  /**
   * Method buildContext.
   *
   * @since 1.0.0
   *
   * @param Notification $notification the notification aggregate
   * @param array<string, mixed> $channelPayload channel-specific payload
   *
   * @return array<string, mixed> the template context
   */
  private function buildContext(Notification $notification, array $channelPayload): array
  {
    $body = $notification->body();
    $channelBody = $channelPayload['body'] ?? null;
    if (is_string($channelBody) && '' !== trim($channelBody)) {
      $body = $channelBody;
    }

    $recipient = $notification->recipientEmail();

    return array_merge(
      [
        'notification' => $notification,
        'type' => $notification->type(),
        'subject' => $notification->subject(),
        'body' => $body,
        'payload' => $notification->payload(),
        'recipientUserId' => $notification->recipientUserId(),
        'recipientEmail' => null !== $recipient ? (string) $recipient : null,
      ],
      $this->extractContext($channelPayload),
    );
  }

  /**
   * Method extractContext.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $channelPayload channel-specific payload
   *
   * @return array<string, mixed> normalized context
   */
  private function extractContext(array $channelPayload): array
  {
    $context = $channelPayload['context'] ?? null;

    if (!is_array($context)) {
      return [];
    }

    $normalized = [];
    foreach ($context as $key => $value) {
      if (!is_string($key)) {
        continue;
      }

      $normalized[$key] = $value;
    }

    return $normalized;
  }
  // #endregion
}
