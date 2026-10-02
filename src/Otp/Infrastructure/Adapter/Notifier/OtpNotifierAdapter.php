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
use RuntimeException;
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Application\Port\Outbound\RequestOriginPort;
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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpNotifierAdapter implements OtpNotifierPort
{
  /**
   * Constant EMAIL_TEMPLATE
   */
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
   * @param ?RequestOriginPort $originResolver transient request context for direct callers
   */
  public function __construct(
    private readonly NotifierInterface $notifier,
    private readonly MailerInterface $mailer,
    private readonly Environment $twig,
    private readonly string $senderEmail = 'noreply@fireguard.local',
    private readonly ?RequestOriginPort $originResolver = null,
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
   * @param ?EmailRequestDetails $details temporary email delivery context
   *
   * @return void no return value
   */
  public function send(Otp $otp, ?EmailRequestDetails $details = null): void
  {
    match ($otp->channel()) {
      OtpChannel::EMAIL => $this->sendEmail($otp, $details),
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
   * @param ?EmailRequestDetails $details temporary email delivery context
   *
   * @return void no return value
   */
  private function sendEmail(Otp $otp, ?EmailRequestDetails $details): void
  {
    try {
      $code = $otp->code()->plain();
    } catch (Throwable) {
      return;
    }

    $subject = $this->getEmailSubject($otp);

    try {
      $email = new Email()
        ->from($this->senderEmail)
        ->to($otp->recipient())
        ->subject($subject)
        ->html($this->renderEmailHtml($otp, $code, $subject, $details));

      $this->mailer->send(message: $email);
    } catch (Throwable $exception) {
      if (null !== $details?->location) {
        // No previous exception: its message could echo the rendered location into logs.
        throw new RuntimeException('OTP email delivery failed: ' . $exception::class);
      }

      throw $exception;
    }
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
      OtpPurpose::LOGIN => 'Your login verification code',
      OtpPurpose::PASSWORD_RESET => 'Your password reset code',
      OtpPurpose::EMAIL_VERIFICATION, OtpPurpose::EMAIL_OWNERSHIP => 'Verify your email address',
      OtpPurpose::PHONE_VERIFICATION => 'Verify your phone number',
      OtpPurpose::SENSITIVE_OPERATION => 'Confirm your action',
      OtpPurpose::TRANSACTION_APPROVAL => 'Approve your transaction',
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
   * @param ?EmailRequestDetails $details temporary email delivery context
   *
   * @return string the HTML content
   */
  private function renderEmailHtml(Otp $otp, string $code, string $subject, ?EmailRequestDetails $details): string
  {
    $details ??= EmailRequestDetails::fromOrigin($this->originResolver?->current(), null);
    $origin = null === $details->browser && null === $details->operatingSystem
      ? null
      : ['browser' => $details->browser, 'operatingSystem' => $details->operatingSystem];
    $minutes = (int) ceil(($otp->expiresAt()->getTimestamp() - time()) / 60);
    if ($minutes < 1) {
      $minutes = 1;
    }

    return $this->twig->render(self::EMAIL_TEMPLATE, [
      'subject' => $subject,
      'code' => $code,
      'instruction' => $this->getEmailInstruction($otp),
      'expiresInMinutes' => $minutes,
      'requestedAt' => $otp->createdAt(),
      'origin' => $origin,
      'requestDetails' => $details,
      'locale' => $details->locale,
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
      OtpPurpose::LOGIN => 'Enter the code below on the Fireguard sign-in verification screen to complete your sign-in.',
      OtpPurpose::PASSWORD_RESET => 'Return to the Fireguard password reset screen and enter the code below to continue resetting your password.',
      OtpPurpose::EMAIL_VERIFICATION, OtpPurpose::EMAIL_OWNERSHIP => 'Enter the code below on the Fireguard email verification screen to confirm that you have access to this email address.',
      OtpPurpose::PHONE_VERIFICATION => 'Enter the code below on the Fireguard phone verification screen to confirm your phone number.',
      OtpPurpose::SENSITIVE_OPERATION => 'Return to the action you started in Fireguard. Check its details, then enter the code below only if you want to confirm it.',
      OtpPurpose::TRANSACTION_APPROVAL => 'Return to the transaction you started in Fireguard. Check its details, then enter the code below only if you want to approve it.',
    };
  }
  // #endregion
}
