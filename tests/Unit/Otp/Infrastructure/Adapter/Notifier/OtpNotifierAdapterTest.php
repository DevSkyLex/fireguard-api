<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Notifier;

use DateTimeImmutable;
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpId, OtpPurpose};
use Otp\Infrastructure\Adapter\Notifier\OtpNotifierAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\{Email, RawMessage};
use Symfony\Component\Notifier\NotifierInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function is_string;

/**
 * Test OtpNotifierAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OtpNotifierAdapter::class)]
final class OtpNotifierAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testSendEmailUsesMailer(): void
  {
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects(self::once())->method('send');

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::never())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
      senderEmail: 'noreply@example.com',
    );

    $adapter->send($otp);
  }

  #[Test]
  public function testSendSmsUsesNotifier(): void
  {
    $otp = $this->createOtp(OtpChannel::SMS, '+1234567890');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects(self::never())->method('send');

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::once())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
    );

    $adapter->send($otp);
  }

  #[Test]
  public function testSendTotpDoesNothing(): void
  {
    $otp = $this->createOtp(OtpChannel::TOTP, 'auth-app');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects(self::never())->method('send');

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::never())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
    );

    $adapter->send($otp);
  }

  #[Test]
  public function testSendEmailSkipsWhenCodeMissing(): void
  {
    $otp = $this->createOtpWithoutPlainCode(OtpChannel::EMAIL, 'user@example.com');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects(self::never())->method('send');

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::never())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
    );

    $adapter->send($otp);
  }

  #[Test]
  public function testSendEmailUsesPurposeSpecificSubject(): void
  {
    $mailer = new FakeMailer();

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::never())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
      senderEmail: 'noreply@example.com',
    );

    $cases = [
      [OtpPurpose::LOGIN, '[FireGuard] Your login verification code'],
      [OtpPurpose::PASSWORD_RESET, '[FireGuard] Your password reset code'],
      [OtpPurpose::EMAIL_VERIFICATION, '[FireGuard] Verify your email address'],
      [OtpPurpose::PHONE_VERIFICATION, '[FireGuard] Verify your phone number'],
      [OtpPurpose::SENSITIVE_OPERATION, '[FireGuard] Confirm your action'],
      [OtpPurpose::TRANSACTION_APPROVAL, '[FireGuard] Approve your transaction'],
    ];

    $expectedSubjects = [];
    foreach ($cases as $case) {
      [$purpose, $expectedSubject] = $case;
      $expectedSubjects[] = $expectedSubject;
      $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com', $purpose);
      $adapter->send($otp);
    }

    self::assertSame($expectedSubjects, $mailer->subjects);
  }

  #[Test]
  public function testSendEmailRendersHtmlFromTwigTemplate(): void
  {
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com', OtpPurpose::PASSWORD_RESET);
    $mailer = new FakeMailer();

    $notifier = $this->createMock(NotifierInterface::class);
    $notifier->expects(self::never())->method('send');

    $adapter = new OtpNotifierAdapter(
      notifier: $notifier,
      mailer: $mailer,
      twig: $this->createTwigEnvironment(),
      senderEmail: 'noreply@example.com',
    );

    $adapter->send($otp);

    self::assertCount(1, $mailer->htmlBodies);
    self::assertStringContainsString('Use the code below to reset your password.', $mailer->htmlBodies[0]);
    self::assertStringContainsString('expires in', $mailer->htmlBodies[0]);
  }

  private function createTwigEnvironment(): Environment
  {
    return new Environment(new ArrayLoader([
      'otp/email/code.html.twig' => '<h1>{{ subject }}</h1><p>{{ instruction }}</p><p>{{ code }}</p><p>expires in {{ expiresInMinutes }}</p>',
    ]));
  }

  private function createOtp(OtpChannel $channel, string $recipient, OtpPurpose $purpose = OtpPurpose::LOGIN): Otp
  {
    return Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: $purpose,
      channel: $channel,
      recipient: $recipient,
    );
  }

  private function createOtpWithoutPlainCode(OtpChannel $channel, string $recipient): Otp
  {
    return Otp::reconstitute(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      challengeToken: ChallengeToken::fromString('challenge'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: $channel,
      codeHash: OtpCode::generate()->hash(),
      recipient: $recipient,
      expiresAt: new DateTimeImmutable('+5 minutes'),
      maxAttempts: 3,
      attempts: 0,
      verifiedAt: null,
      createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
    );
  }
  // #endregion
}

final class FakeMailer implements MailerInterface
{
  /**
   * @var list<string>
   */
  public array $subjects = [];

  /**
   * @var list<string>
   */
  public array $htmlBodies = [];

  public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
  {
    if ($message instanceof Email) {
      $this->subjects[] = $message->getSubject() ?? '';
      $htmlBody = $message->getHtmlBody();
      if (is_string($htmlBody)) {
        $this->htmlBodies[] = $htmlBody;
      }
    }
  }
}
