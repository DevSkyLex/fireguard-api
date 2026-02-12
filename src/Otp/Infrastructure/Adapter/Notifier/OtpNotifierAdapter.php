<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Notifier;

use Otp\Application\Port\Outbound\Challenge\OtpNotifierPort;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{
  OtpChannel,
  OtpPurpose
};
use Otp\Infrastructure\Notification\OtpNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Throwable;
use Twig\Environment;

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
  private const string EMAIL_TEMPLATE = 'otp/email/code.html.twig';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the adapter with the Symfony
   * Notifier and Mailer.
   *
   * @since 1.0.0
   *
   * @param NotifierInterface $notifier the Symfony Notifier
   * @param MailerInterface $mailer the Symfony Mailer for email fallback
   * @param Environment $twig the Twig renderer
   * @param string $senderEmail the sender email address
   */
  public function __construct(
    private readonly NotifierInterface $notifier,
    private readonly MailerInterface $mailer,
    private readonly Environment $twig,
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

    $subject = $this->getEmailSubject($otp);

    $email = new Email()
      ->from($this->senderEmail)
      ->to($otp->recipient())
      ->subject($subject)
      ->html($this->renderEmailHtml($otp, $code, $subject));

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
    $notification = new OtpNotification(otp: $otp);
    $recipient = new Recipient(phone: $otp->recipient());

    $this->notifier->send(
      notification: $notification,
      recipient: $recipient,
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
   * Method renderEmailHtml.
   *
   * Renders the HTML email content from Twig template.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   * @param string $code the code
   * @param string $subject the subject
   *
   * @return string the HTML content
   */
  private function renderEmailHtml(Otp $otp, string $code, string $subject): string
  {
    $minutes = (int) ceil(($otp->expiresAt()->getTimestamp() - time()) / 60);
    if ($minutes < 1) {
      $minutes = 1;
    }

    return $this->twig->render(self::EMAIL_TEMPLATE, [
      'subject' => $subject,
      'code' => $code,
      'instruction' => $this->getEmailInstruction($otp),
      'expiresInMinutes' => $minutes,
    ]);
  }

  /**
   * Method getEmailInstruction.
   *
   * Returns purpose-specific instructions for OTP emails.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP
   *
   * @return string the instruction text
   */
  private function getEmailInstruction(Otp $otp): string
  {
    return match ($otp->purpose()) {
      OtpPurpose::LOGIN => 'Use the code below to complete your sign-in.',
      OtpPurpose::PASSWORD_RESET => 'Use the code below to reset your password.',
      OtpPurpose::EMAIL_VERIFICATION => 'Use the code below to verify your email address.',
      OtpPurpose::PHONE_VERIFICATION => 'Use the code below to verify your phone number.',
      OtpPurpose::SENSITIVE_OPERATION => 'Use the code below to confirm this sensitive action.',
      OtpPurpose::TRANSACTION_APPROVAL => 'Use the code below to approve this transaction.',
    };
  }
  // #endregion
}
