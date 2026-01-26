<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Notification;

use DateTimeImmutable;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use Otp\Infrastructure\Notification\OtpNotification;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Test OtpNotificationTest.
 *
 * @category Notification Tests
 */
#[CoversClass(className: OtpNotification::class)]
final class OtpNotificationTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testChannelsForEmailAndSmsAndTotp(): void
  {
    $emailOtp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com');
    $smsOtp = $this->createOtp(OtpChannel::SMS, '+12025550123');
    $totpOtp = $this->createOtp(OtpChannel::TOTP, 'unused');

    $recipient = new Recipient('', '+12025550123');

    self::assertSame(['email'], new OtpNotification($emailOtp)->getChannels($recipient));
    self::assertSame(['sms'], new OtpNotification($smsOtp)->getChannels($recipient));
    self::assertSame([], new OtpNotification($totpOtp)->getChannels($recipient));
  }

  #[Test]
  public function testAsSmsMessageReturnsMessageForSmsChannel(): void
  {
    $otp = $this->createOtp(OtpChannel::SMS, '+12025550123');
    $notification = new OtpNotification($otp);

    $message = $notification->asSmsMessage(new Recipient('', '+12025550123'));

    self::assertInstanceOf(SmsMessage::class, $message);
    self::assertSame('+12025550123', $message->getPhone());
    self::assertStringContainsString('FireGuard', $message->getSubject());
  }

  #[Test]
  public function testAsSmsMessageReturnsNullForNonSmsChannel(): void
  {
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com');
    $notification = new OtpNotification($otp);

    self::assertNull($notification->asSmsMessage(new Recipient('', '+12025550123')));
  }

  #[Test]
  #[DataProvider('otpSubjectProvider')]
  public function testSubjectMatchesPurpose(OtpPurpose $purpose, string $expected): void
  {
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com', $purpose);
    $notification = new OtpNotification($otp);

    self::assertSame($expected, $notification->getOtpSubject());
  }

  #[Test]
  public function testAsSmsMessageUsesMaskedCodeWhenPlainMissing(): void
  {
    $otp = Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      challengeToken: ChallengeToken::fromString('challenge'),
      userId: 'user-1',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::SMS,
      codeHash: OtpCode::generate()->hash(),
      recipient: '+12025550123',
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 3,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
    );

    $notification = new OtpNotification($otp);

    $message = $notification->asSmsMessage(new Recipient('', '+12025550123'));

    self::assertInstanceOf(SmsMessage::class, $message);
    self::assertStringContainsString('******', $message->getSubject());
  }
  // #endregion

  /**
   * @return array<string, array{0: OtpPurpose, 1: string}>
   */
  public static function otpSubjectProvider(): array
  {
    return [
      'login' => [OtpPurpose::LOGIN, 'Your login verification code'],
      'password_reset' => [OtpPurpose::PASSWORD_RESET, 'Your password reset code'],
      'email_verification' => [OtpPurpose::EMAIL_VERIFICATION, 'Verify your email address'],
      'phone_verification' => [OtpPurpose::PHONE_VERIFICATION, 'Verify your phone number'],
      'sensitive_operation' => [OtpPurpose::SENSITIVE_OPERATION, 'Confirm your action'],
      'transaction_approval' => [OtpPurpose::TRANSACTION_APPROVAL, 'Approve your transaction'],
    ];
  }

  private function createOtp(OtpChannel $channel, string $recipient, OtpPurpose $purpose = OtpPurpose::LOGIN): Otp
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-1',
      purpose: $purpose,
      channel: $channel,
      recipient: $recipient,
    );
    $otp->releaseEvents();

    return $otp;
  }
}
