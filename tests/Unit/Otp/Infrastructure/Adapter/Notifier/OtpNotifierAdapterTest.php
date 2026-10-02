<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Adapter\Notifier;

use DateTimeImmutable;
use DateTimeZone;
use Otp\Domain\Model\{Otp, OtpRestoredIdentity, OtpRestoredProgress};
use Otp\Domain\ValueObject\{ChallengeToken, OtpChannel, OtpCode, OtpGenerationOptions, OtpId, OtpPurpose};
use Otp\Infrastructure\Adapter\Notifier\OtpNotifierAdapter;
use Otp\Infrastructure\Exception\OtpEmailDeliveryException;
use Otp\Infrastructure\Notification\RequestOriginResolver;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\GeoIp\IpLocation;
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Application\Port\Outbound\RequestOriginPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
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
  public function testExplicitEmptyContextDeliversWithoutResolvingOriginAgain(): void
  {
    $origin = $this->createMock(RequestOriginPort::class);
    $origin->expects(self::never())->method('current')->willThrowException(new RuntimeException('origin unavailable'));
    $mailer = new FakeMailer();
    $adapter = new OtpNotifierAdapter(
      notifier: $this->createStub(NotifierInterface::class),
      mailer: $mailer,
      twig: new Environment(new ArrayLoader([
        'otp/email/code.html.twig' => '{{ origin is null ? "no-origin" : "origin" }}|{{ code }}',
      ])),
      originResolver: $origin,
    );
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com');

    $adapter->send($otp, EmailRequestDetails::fromOrigin(null, null));

    self::assertSame(['no-origin|' . $otp->code()->plain()], $mailer->htmlBodies);
    self::assertSame(['Your login verification code'], $mailer->subjects);
  }

  #[Test]
  public function testLocationBearingDeliveryFailureCannotExposeProviderExceptionContent(): void
  {
    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects(self::once())->method('send')->willThrowException(new RuntimeException('Paris FR 8.8.8.8'));
    $adapter = new OtpNotifierAdapter(
      $this->createStub(NotifierInterface::class),
      $mailer,
      $this->createTwigEnvironment(),
    );

    try {
      $adapter->send(
        $this->createOtp(OtpChannel::EMAIL, 'user@example.com'),
        new EmailRequestDetails(location: new IpLocation('FR', 'Paris')),
      );
      self::fail('The delivery failure must remain visible to the caller.');
    } catch (OtpEmailDeliveryException $exception) {
      self::assertSame('OTP email delivery failed: RuntimeException', $exception->getMessage());
      self::assertNull($exception->getPrevious());
    }
  }

  #[Test]
  public function testDeliveryWithoutLocationPreservesTheProviderFailure(): void
  {
    foreach ([null, EmailRequestDetails::fromOrigin(null, null)] as $details) {
      $failure = new RuntimeException('Provider unavailable');
      $mailer = $this->createMock(MailerInterface::class);
      $mailer->expects(self::once())->method('send')->willThrowException($failure);
      $adapter = new OtpNotifierAdapter(
        $this->createStub(NotifierInterface::class),
        $mailer,
        $this->createTwigEnvironment(),
      );

      try {
        $adapter->send($this->createOtp(OtpChannel::EMAIL, 'user@example.com'), $details);
        self::fail('The delivery failure must remain visible to the caller.');
      } catch (RuntimeException $exception) {
        self::assertSame($failure, $exception);
      }
    }
  }

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
      [OtpPurpose::LOGIN, 'Your login verification code'],
      [OtpPurpose::PASSWORD_RESET, 'Your password reset code'],
      [OtpPurpose::EMAIL_VERIFICATION, 'Verify your email address'],
      [OtpPurpose::EMAIL_OWNERSHIP, 'Verify your email address'],
      [OtpPurpose::PHONE_VERIFICATION, 'Verify your phone number'],
      [OtpPurpose::SENSITIVE_OPERATION, 'Confirm your action'],
      [OtpPurpose::TRANSACTION_APPROVAL, 'Approve your transaction'],
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
    self::assertStringContainsString('Return to the Fireguard password reset screen and enter the code below', $mailer->htmlBodies[0]);
    self::assertStringContainsString('expires in', $mailer->htmlBodies[0]);
  }

  #[Test]
  public function testSendEmailPassesTheRequestTimeAndOriginToTheTemplate(): void
  {
    $request = Request::create('/api/auth/login', 'POST');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
    $stack = new RequestStack();
    $stack->push($request);
    $mailer = new FakeMailer();

    $adapter = new OtpNotifierAdapter(
      notifier: $this->createStub(NotifierInterface::class),
      mailer: $mailer,
      twig: new Environment(new ArrayLoader([
        'otp/email/code.html.twig' => '{{ origin.browser }}|{{ origin.operatingSystem }}|{{ requestedAt|date("Y-m-d H:i", "UTC") }}',
      ])),
      senderEmail: 'noreply@example.com',
      originResolver: new RequestOriginResolver($stack),
    );
    $otp = $this->createOtp(OtpChannel::EMAIL, 'user@example.com');

    $adapter->send($otp);

    self::assertSame(['Chrome|Windows|' . $otp->createdAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i')], $mailer->htmlBodies);
  }

  #[Test]
  public function testSendEmailHasNoOriginOutsideAnHttpRequest(): void
  {
    $mailer = new FakeMailer();

    $adapter = new OtpNotifierAdapter(
      notifier: $this->createStub(NotifierInterface::class),
      mailer: $mailer,
      twig: new Environment(new ArrayLoader([
        'otp/email/code.html.twig' => '{{ origin is null ? "no-origin" : "origin" }}',
      ])),
    );

    $adapter->send($this->createOtp(OtpChannel::EMAIL, 'user@example.com'));

    self::assertSame(['no-origin'], $mailer->htmlBodies);
  }

  #[Test]
  public function testSendEmailClampsAnAlreadyElapsedTtlToOneMinute(): void
  {
    $otp = Otp::generate(
      id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
      userId: 'user-123',
      purpose: OtpPurpose::LOGIN,
      channel: OtpChannel::EMAIL,
      recipient: 'user@example.com',
      options: new OtpGenerationOptions(ttlSeconds: 0),
    );
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
    self::assertStringContainsString('expires in 1', $mailer->htmlBodies[0]);
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
      identity: new OtpRestoredIdentity(
        id: new OtpId('123e4567-e89b-12d3-a456-426614174000'),
        challengeToken: ChallengeToken::fromString('challenge'),
        userId: 'user-123',
        purpose: OtpPurpose::LOGIN,
        channel: $channel,
        recipient: $recipient,
      ),
      progress: new OtpRestoredProgress(
        codeHash: OtpCode::generate()->hash(),
        expiresAt: new DateTimeImmutable('+5 minutes'),
        maxAttempts: 3,
        attempts: 0,
        verifiedAt: null,
        createdAt: new DateTimeImmutable('2024-01-01 00:00:00'),
      ),
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
