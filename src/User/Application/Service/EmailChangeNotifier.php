<?php

declare(strict_types=1);

namespace User\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, NotificationType, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function rtrim;
use function sprintf;

/**
 * Service EmailChangeNotifier.
 *
 * Shared email-change notification logic: the confirmation email sent
 * to the NEW address (carrying the raw token link) and the short
 * notices sent to the OLD address (change pending, change effective).
 * Mirrors the organization invitation notifier so the email contract
 * lives in a single place.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailChangeNotifier
{
  /**
   * Email locales the email-change templates support; any other
   * recipient locale falls back to English.
   */
  private const array SUPPORTED_LOCALES = ['en', 'fr', 'es'];

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the EmailChangeNotifier class.
   *
   * @since 1.0.0
   *
   * @param NotificationPort $notificationPort the notification module port
   * @param string $frontendUrl the public frontend base URL for the confirm link
   * @param TranslatorInterface $translator the translator for the localized subject and body
   */
  public function __construct(
    private NotificationPort $notificationPort,
    private string $frontendUrl,
    private TranslatorInterface $translator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method buildConfirmUrl.
   *
   * Builds the public frontend link the recipient follows to confirm
   * the email change, carrying the raw token as a query parameter.
   *
   * @since 1.0.0
   *
   * @param string $token the raw confirmation token
   *
   * @return string the absolute confirm URL
   */
  public function buildConfirmUrl(string $token): string
  {
    return sprintf(
      '%s/account/email-change/confirm?token=%s',
      rtrim($this->frontendUrl, '/'),
      $token,
    );
  }

  /**
   * Method sendConfirmation.
   *
   * Sends the confirmation email — with the token link — to the NEW
   * address. Possession of this token is what proves the new mailbox.
   *
   * @since 1.0.0
   *
   * @param string $newEmail the new address (recipient)
   * @param string $confirmUrl the confirmation link carrying the raw token
   * @param DateTimeImmutable $expiresAt when the token expires
   * @param string $locale the recipient locale for the email template (en/fr/es)
   *
   * @return SentNotification the sent notification result
   */
  public function sendConfirmation(
    string $newEmail,
    string $confirmUrl,
    DateTimeImmutable $expiresAt,
    string $locale = 'en',
  ): SentNotification {
    $subject = $this->translator->trans('emailChange.confirmSubject', [], 'emails', $locale);

    return $this->notificationPort->send(new SendNotificationRequest(
      type: NotificationType::USER_EMAIL_CHANGE_REQUESTED,
      subject: $subject,
      body: sprintf(
        '<p>%s</p>',
        $this->translator->trans('emailChange.confirmHeading', [], 'emails', $locale),
      ),
      channels: [NotificationChannel::EMAIL],
      payload: [
        'expiresAt' => $expiresAt->format('c'),
      ],
      deliveryPayload: [
        NotificationChannel::EMAIL->value => [
          'template' => 'notification/email/user_email_change_confirm.html.twig',
          'context' => [
            'confirmUrl' => $confirmUrl,
            'expiresAt' => $this->formatExpiresAt($expiresAt, $locale),
            'locale' => $locale,
          ],
        ],
      ],
      recipientEmail: $newEmail,
    ));
  }

  /**
   * Method sendPendingNotice.
   *
   * Sends the short notice to the OLD address that an email change is
   * in progress, so the legitimate owner can react to a hijack.
   *
   * @since 1.0.0
   *
   * @param string $currentEmail the current (old) address (recipient)
   * @param string $locale the recipient locale for the email template (en/fr/es)
   *
   * @return SentNotification the sent notification result
   */
  public function sendPendingNotice(string $currentEmail, string $locale = 'en'): SentNotification
  {
    return $this->sendNotice(
      recipientEmail: $currentEmail,
      type: NotificationType::USER_EMAIL_CHANGE_REQUESTED,
      subjectKey: 'emailChange.pendingSubject',
      headingKey: 'emailChange.pendingHeading',
      bodyKey: 'emailChange.pendingBody',
      locale: $locale,
    );
  }

  /**
   * Method sendChangedNotice.
   *
   * Sends the short notice to the OLD address that the change is now
   * effective and every session has been signed out.
   *
   * @since 1.0.0
   *
   * @param string $previousEmail the previous (old) address (recipient)
   * @param string $locale the recipient locale for the email template (en/fr/es)
   *
   * @return SentNotification the sent notification result
   */
  public function sendChangedNotice(string $previousEmail, string $locale = 'en'): SentNotification
  {
    return $this->sendNotice(
      recipientEmail: $previousEmail,
      type: NotificationType::USER_EMAIL_CHANGE_CONFIRMED,
      subjectKey: 'emailChange.changedSubject',
      headingKey: 'emailChange.changedHeading',
      bodyKey: 'emailChange.changedBody',
      locale: $locale,
    );
  }

  /**
   * Method clampLocale.
   *
   * Clamps a recipient's raw locale to one the email-change templates
   * actually support, defaulting to English.
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
   * Method sendNotice.
   *
   * Sends one of the short old-address notices through the
   * notification module.
   *
   * @since 1.0.0
   *
   * @param string $recipientEmail the recipient address
   * @param string $type the notification type constant
   * @param string $subjectKey the translation key for the subject
   * @param string $headingKey the translation key for the heading
   * @param string $bodyKey the translation key for the body paragraph
   * @param string $locale the recipient locale (en/fr/es)
   *
   * @return SentNotification the sent notification result
   */
  private function sendNotice(
    string $recipientEmail,
    string $type,
    string $subjectKey,
    string $headingKey,
    string $bodyKey,
    string $locale,
  ): SentNotification {
    $subject = $this->translator->trans($subjectKey, [], 'emails', $locale);

    return $this->notificationPort->send(new SendNotificationRequest(
      type: $type,
      subject: $subject,
      body: sprintf(
        '<p>%s</p>',
        $this->translator->trans($bodyKey, [], 'emails', $locale),
      ),
      channels: [NotificationChannel::EMAIL],
      deliveryPayload: [
        NotificationChannel::EMAIL->value => [
          'template' => 'notification/email/user_email_change_notice.html.twig',
          'context' => [
            'headingKey' => $headingKey,
            'bodyKey' => $bodyKey,
            'locale' => $locale,
          ],
        ],
      ],
      recipientEmail: $recipientEmail,
    ));
  }

  /**
   * Method formatExpiresAt.
   *
   * Formats the token expiry datetime using a locale-appropriate
   * pattern.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $expiresAt the token expiration datetime
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
