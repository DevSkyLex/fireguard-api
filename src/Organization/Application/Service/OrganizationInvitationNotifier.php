<?php

declare(strict_types=1);

namespace Organization\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Symfony\Contracts\Translation\TranslatorInterface;

use function htmlspecialchars;
use function in_array;
use function rtrim;
use function sprintf;

/**
 * Service OrganizationInvitationNotifier.
 *
 * Shared invitation token, accept-link and notification logic reused by the
 * invite and resend use cases so the email contract lives in a single place.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationInvitationNotifier
{
  /**
   * Email locales the invitation templates support; any other recipient locale
   * falls back to English. Single source shared by the invite and resend flows.
   */
  private const array SUPPORTED_LOCALES = ['en', 'fr', 'es'];

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationInvitationNotifier class.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notificationPort the notification module port
   * @param string $frontendUrl the public frontend base URL for the accept link
   * @param OrganizationInvitationTokenHasher $tokenHasher the shared token generator/hasher
   * @param TranslatorInterface $translator the translator, for the localized subject and body
   */
  public function __construct(
    private NotificationPort $notificationPort,
    private string $frontendUrl,
    private OrganizationInvitationTokenHasher $tokenHasher,
    private TranslatorInterface $translator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method generateToken.
   *
   * Generates a cryptographically secure raw invitation token.
   *
   * @since 1.0.0
   *
   * @return string the raw invitation token
   */
  public function generateToken(): string
  {
    return $this->tokenHasher->generate();
  }

  /**
   * Method hashToken.
   *
   * Computes the deterministic hash stored for invitation token lookup.
   *
   * @since 1.0.0
   *
   * @param string $token the raw token
   *
   * @return string the token hash
   */
  public function hashToken(string $token): string
  {
    return $this->tokenHasher->hash($token);
  }

  /**
   * Method buildAcceptUrl.
   *
   * Builds the public frontend link a recipient follows to accept the
   * invitation, carrying the raw token as a query parameter.
   *
   * @since 1.0.0
   *
   * @param string $token the raw invitation token
   *
   * @return string the absolute accept URL
   */
  public function buildAcceptUrl(string $token): string
  {
    return sprintf(
      '%s/organizations/invitations/accept?token=%s',
      rtrim($this->frontendUrl, '/'),
      $token,
    );
  }

  /**
   * Method send.
   *
   * Sends invitation instructions through the notification module.
   *
   * @since 1.0.0
   *
   * @param string $organizationName the organization name
   * @param string $email the recipient email
   * @param string $acceptUrl the invitation accept link
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   * @param string|null $recipientUserId the recipient user identifier
   * @param string $locale the recipient locale for the email template (en/fr/es)
   *
   * @return SentNotification the sent notification result
   */
  public function send(
    string $organizationName,
    string $email,
    string $acceptUrl,
    DateTimeImmutable $expiresAt,
    ?string $recipientUserId = null,
    string $locale = 'en',
  ): SentNotification {
    $expiresAtIso = $expiresAt->format('c');

    $channels = [NotificationChannel::EMAIL];
    if (null !== $recipientUserId) {
      $channels[] = NotificationChannel::MERCURE;
    }

    $subject = $this->translator->trans(
      'invitation.emailSubject',
      ['%org%' => $organizationName],
      'emails',
      $locale,
    );

    return $this->notificationPort->send(new SendNotificationRequest(
      type: NotificationType::ORGANIZATION_INVITATION,
      subject: $subject,
      body: sprintf(
        '<p>%s</p><p>%s</p>',
        $this->translator->trans(
          'invitation.heading',
          ['%org%' => htmlspecialchars($organizationName)],
          'emails',
          $locale,
        ),
        $this->translator->trans(
          'invitation.expires',
          ['%date%' => $this->formatExpiresAt($expiresAt, $locale)],
          'emails',
          $locale,
        ),
      ),
      channels: $channels,
      payload: [
        'organizationName' => $organizationName,
        'expiresAt' => $expiresAtIso,
      ],
      deliveryPayload: [
        NotificationChannel::EMAIL->value => [
          'template' => 'notification/email/organization_invitation.html.twig',
          'context' => [
            'organizationName' => $organizationName,
            'acceptUrl' => $acceptUrl,
            'expiresAt' => $this->formatExpiresAt($expiresAt, $locale),
            'locale' => $locale,
          ],
        ],
      ],
      recipientUserId: $recipientUserId,
      recipientEmail: $email,
    ));
  }

  /**
   * Method clampLocale.
   *
   * Clamps a recipient's raw locale to one the invitation email actually
   * supports, defaulting to English. Single source of the supported-locale list
   * for the invite and resend use cases.
   *
   * @since 1.0.0
   *
   * @param string|null $locale the recipient's raw locale, if any
   *
   * @return string a supported email locale (en/fr/es)
   */
  public function clampLocale(?string $locale): string
  {
    return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'en';
  }

  /**
   * Method formatExpiresAt.
   *
   * Formats the invitation expiry datetime using a locale-appropriate
   * pattern: the day/month order English readers expect, and the numeric
   * day/month/year order fr/es readers expect.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   * @param string $locale the recipient locale (en/fr/es)
   *
   * @return string the formatted expiry datetime
   */
  private function formatExpiresAt(DateTimeImmutable $expiresAt, string $locale): string
  {
    $format = match ($locale) {
      'fr', 'es' => 'd/m/Y H:i',
      default => 'F j, Y, g:i A',
    };

    return $expiresAt->format($format);
  }
  // #endregion
}
