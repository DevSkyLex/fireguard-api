<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Symfony\Notification;

use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{
  OtpChannel,
  OtpPurpose,
};
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Throwable;

use function ceil;
use function sprintf;
use function time;

/**
 * Notification OtpNotification.
 *
 * @category Notification
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpNotification extends Notification implements SmsNotificationInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * OtpNotification class.
   *
   * @since 1.0.0
   *
   * @param Otp $otp the OTP to send
   */
  public function __construct(private readonly Otp $otp)
  {
    parent::__construct(
      subject: $this->getOtpSubject(),
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method getChannels
   * {@inheritDoc}
   *
   * Returns the notification channels.
   *
   * @since 1.0.0
   *
   * @param RecipientInterface $recipient the recipient
   *
   * @return array<string> the channels
   */
  public function getChannels(RecipientInterface $recipient): array
  {
    return match ($this->otp->channel()) {
      OtpChannel::EMAIL => ['email'],
      OtpChannel::SMS => ['sms'],
      OtpChannel::TOTP => [],
    };
  }

  /**
   * Method asSmsMessage
   * {@inheritDoc}
   *
   * Returns the notification SMS message.
   *
   * @since 1.0.0
   *
   * @param SmsRecipientInterface $recipient the recipient
   * @param string|null $transport the transport
   *
   * @return SmsMessage|null the SMS message
   */
  public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
  {
    if (OtpChannel::SMS !== $this->otp->channel()) {
      return null;
    }

    return new SmsMessage(
      phone: $this->otp->recipient(),
      subject: $this->getSmsContent(),
    );
  }

  /**
   * Method getOtpSubject.
   *
   * Returns the notification subject.
   *
   * @since 1.0.0
   *
   * @return string the subject
   */
  public function getOtpSubject(): string
  {
    return match ($this->otp->purpose()) {
      OtpPurpose::LOGIN => 'Your login verification code',
      OtpPurpose::PASSWORD_RESET => 'Your password reset code',
      OtpPurpose::EMAIL_VERIFICATION => 'Verify your email address',
      OtpPurpose::PHONE_VERIFICATION => 'Verify your phone number',
      OtpPurpose::SENSITIVE_OPERATION => 'Confirm your action',
      OtpPurpose::TRANSACTION_APPROVAL => 'Approve your transaction',
    };
  }

  /**
   * Method getOtp.
   *
   * Returns the OTP.
   *
   * @since 1.0.0
   *
   * @return Otp the OTP
   */
  public function getOtp(): Otp
  {
    return $this->otp;
  }

  /**
   * Method getSmsContent.
   *
   * Returns the SMS content.
   *
   * @since 1.0.0
   *
   * @return string the SMS content
   */
  private function getSmsContent(): string
  {
    try {
      $code = $this->otp->code()->plain();
    } catch (Throwable) {
      $code = '******';
    }

    return sprintf(
      '[FireGuard] Your verification code is: %s. Valid for %d minutes.',
      $code,
      (int) ceil(($this->otp->expiresAt()->getTimestamp() - time()) / 60),
    );
  }
  // #endregion
}
