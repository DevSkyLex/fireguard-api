<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Symfony\Notification;

use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{
  OtpChannel,
  OtpPurpose,
};
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;
use Throwable;

use function sprintf;

/**
 * Notification OtpNotification
 * @final
 *
 * Symfony Notifier notification for OTP delivery.
 *
 * @category Notification
 * @package Otp\Infrastructure\Symfony\Notification
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpNotification extends Notification implements SmsNotificationInterface
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * OtpNotification class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Otp $otp The OTP to send.
   */
  public function __construct(private readonly Otp $otp)
  {
    parent::__construct(
      subject: $this->getOtpSubject(),
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method getChannels
   * {@inheritDoc}
   *
   * Returns the notification channels.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RecipientInterface $recipient The recipient.
   *
   * @return array<string> The channels.
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
   * @access public
   * @since 1.0.0
   *
   * @param SmsRecipientInterface $recipient The recipient.
   * @param string|null $transport The transport.
   *
   * @return SmsMessage|null The SMS message.
   */
  public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
  {
    if ($this->otp->channel() !== OtpChannel::SMS) {
      return null;
    }

    return new SmsMessage(
      phone: $this->otp->recipient(),
      subject: $this->getSmsContent(),
    );
  }

  /**
   * Method getOtpSubject
   *
   * Returns the notification subject.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The subject.
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
   * Method getSmsContent
   *
   * Returns the SMS content.
   *
   * @access private
   * @since 1.0.0
   *
   * @return string The SMS content.
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
      (int) ceil(($this->otp->expiresAt()->getTimestamp() - time()) / 60)
    );
  }

  /**
   * Method getOtp
   *
   * Returns the OTP.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Otp The OTP.
   */
  public function getOtp(): Otp
  {
    return $this->otp;
  }
  //#endregion
}
