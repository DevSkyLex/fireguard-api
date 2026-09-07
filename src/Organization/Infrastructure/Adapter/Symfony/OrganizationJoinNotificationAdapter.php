<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Symfony;

use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\OrganizationJoinNotificationPort;
use Symfony\Contracts\Translation\TranslatorInterface;
use User\Application\Port\Inbound\EmailOwnershipPort;

use function in_array;

/**
 * Localized recipient notification; no organization data leaks into delivery payloads.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinNotificationAdapter implements OrganizationJoinNotificationPort
{
  /**
   * @since 1.0.0
   *
   * @param NotificationPort $notifications delivery
   * @param EmailOwnershipPort $emails authoritative address
   * @param TranslatorInterface $translator localized copy
   */
  public function __construct(private NotificationPort $notifications, private EmailOwnershipPort $emails, private TranslatorInterface $translator)
  {
  }

  public function send(string $recipientUserId, string $organizationId, string $requestId, string $status): void
  {
    $recipient = $this->emails->get($recipientUserId);
    $locale = in_array($recipient->locale, ['en', 'fr', 'es'], true) ? $recipient->locale : 'en';
    $this->notifications->send(new SendNotificationRequest(
      type: 'organization.join_request.' . $status,
      subject: $this->translator->trans('subject', [], 'organization_join', $locale),
      body: $this->translator->trans('status.' . $status, [], 'organization_join', $locale),
      channels: [NotificationChannel::EMAIL, NotificationChannel::MERCURE],
      payload: ['organizationId' => $organizationId, 'requestId' => $requestId, 'status' => $status],
      recipientUserId: $recipientUserId,
      recipientEmail: $recipient->email,
      organizationId: $organizationId,
    ));
  }
}
