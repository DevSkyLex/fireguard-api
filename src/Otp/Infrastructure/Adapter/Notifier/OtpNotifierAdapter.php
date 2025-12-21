<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Notifier;

use Otp\Application\Port\Outbound\OtpNotifierPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{
  OtpChannel,
  OtpPurpose
};
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Throwable;

use function ceil;
use function time;

/**
 * Adapter OtpNotifierAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpNotifierAdapter implements OtpNotifierPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the adapter with the Symfony
   * Notifier and Mailer.
   *
   * @since 1.0.0
   *
   * @param NotifierInterface $notifier    the Symfony Notifier
   * @param MailerInterface   $mailer      the Symfony Mailer for email fallback
   * @param string            $senderEmail the sender email address
   */
  public function __construct(
    private readonly NotifierInterface $notifier,
    private readonly MailerInterface $mailer,
    private readonly string $senderEmail = 'noreply@fireguard.local',
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method send
   * {@inheritDoc}
   *
   * Sends the OTP to the recipient.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   *
   * @return void no return value
   */
  public function send(Otp $otp): void
  {
    if (!$otp->channel()->requiresDelivery()) {
      return;
    }

    match ($otp->channel()) {
      OtpChannel::EMAIL => $this->sendEmail($otp),
      OtpChannel::SMS => $this->sendSms($otp),
      OtpChannel::TOTP => null,
    };
  }

  /**
   * Method sendEmail.
   *
   * Sends OTP via email.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   *
   * @return void no return value
   */
  private function sendEmail(Otp $otp): void
  {
    try {
      $code = $otp->code()->plain();
    } catch (Throwable) {
      return;
    }

    $email = (new Email())
      ->from($this->senderEmail)
      ->to($otp->recipient())
      ->subject($this->getEmailSubject($otp))
      ->html($this->getEmailHtml($otp, $code));

    $this->mailer->send(message: $email);
  }

  /**
   * Method sendSms.
   *
   * Sends OTP via SMS using Notifier.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   *
   * @return void no return value
   */
  private function sendSms(Otp $otp): void
  {
    $notification = new \Otp\Infrastructure\Symfony\Notification\OtpNotification(otp: $otp);
    $recipient = new Recipient(phone: $otp->recipient());

    $this->notifier->send(
      notification: $notification,
      recipient: $recipient
    );
  }

  /**
   * Method getEmailSubject.
   *
   * Returns the email subject.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   *
   * @return string the subject
   */
  private function getEmailSubject(Otp $otp): string
  {
    return match ($otp->purpose()) {
      OtpPurpose::LOGIN => '[FireGuard] Your login verification code',
      OtpPurpose::PASSWORD_RESET => '[FireGuard] Your password reset code',
      OtpPurpose::EMAIL_VERIFICATION => '[FireGuard] Verify your email address',
      OtpPurpose::PHONE_VERIFICATION => '[FireGuard] Verify your phone number',
      OtpPurpose::SENSITIVE_OPERATION => '[FireGuard] Confirm your action',
      OtpPurpose::TRANSACTION_APPROVAL => '[FireGuard] Approve your transaction',
    };
  }

  /**
   * Method getEmailHtml.
   *
   * Returns the HTML email content.
   *
   * @since 1.0.0
   *
   * @param Otp    $otp  the OTP
   * @param string $code the code
   *
   * @return string the HTML content
   */
  private function getEmailHtml(Otp $otp, string $code): string
  {
    $minutes = (int) ceil(($otp->expiresAt()->getTimestamp() - time()) / 60);

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2563eb; text-align: center; padding: 20px; background: #f1f5f9; border-radius: 8px; margin: 20px 0; }
        .footer { color: #64748b; font-size: 12px; margin-top: 20px; }
      </style>
    </head>
    <body>
      <div class="container">
        <h2>Your verification code</h2>
        <p>Use the code below to complete your verification:</p>
        <div class="code">{$code}</div>
        <p>This code expires in <strong>{$minutes} minutes</strong>.</p>
        <p>If you didn't request this code, please ignore this email.</p>
        <div class="footer">
          <p>FireGuard Auth - Secure Authentication Service</p>
        </div>
      </div>
    </body>
    </html>
    HTML;
  }
  // #endregion
}
