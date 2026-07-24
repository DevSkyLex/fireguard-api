<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset;

use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\{
  RequestPasswordResetCommand,
  RequestPasswordResetHandler
};
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Service\ChallengeResendPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

/**
 * Test RequestPasswordResetHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestPasswordResetHandler::class)]
final class RequestPasswordResetHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string USER_EMAIL = 'jdoe@example.com';

  private const string USER_PASSWORD = 'CurrentP@ssw0rd!';

  // #region Methods
  #[Test]
  public function testReturnsSilentSuccessWhenUserNotFound(): void
  {
    $repo = $this->createStub(UserRepositoryPort::class);
    $repo->method('findByEmail')->willReturn(null);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('generate');

    $handler = new RequestPasswordResetHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordResetCommand(email: 'unknown@example.com'));

    self::assertTrue($result->success);
    self::assertNull($result->challengeToken);
    self::assertNull($result->maskedRecipient);
    self::assertNull($result->expiresAt);
    self::assertNull($result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
  }

  #[Test]
  public function testReturnsSilentSuccessWhenUserCannotLogin(): void
  {
    $user = $this->makePendingUser();

    $repo = $this->createStub(UserRepositoryPort::class);
    $repo->method('findByEmail')->willReturn($user);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('generate');

    $handler = new RequestPasswordResetHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordResetCommand(email: self::USER_EMAIL));

    self::assertTrue($result->success);
    self::assertNull($result->challengeToken);
    self::assertNull($result->maskedRecipient);
    self::assertNull($result->expiresAt);
    self::assertNull($result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
  }

  #[Test]
  public function testGeneratesOtpChallengeWhenUserCanLogin(): void
  {
    $user = $this->makeActiveUser();
    $expiresAt = new DateTimeImmutable('+15 minutes');

    $repo = $this->createStub(UserRepositoryPort::class);
    $repo->method('findByEmail')->willReturn($user);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::once())
      ->method('generate')
      ->with(
        self::USER_ID,
        OtpPurpose::PASSWORD_RESET,
        OtpChannel::EMAIL,
        self::USER_EMAIL,
      )
      ->willReturn(new ChallengeInfo(
        challengeToken: 'challenge-token-123',
        maskedRecipient: 'j***e@example.com',
        expiresAt: $expiresAt,
        maxAttempts: 5,
      ));

    $handler = new RequestPasswordResetHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordResetCommand(email: self::USER_EMAIL));

    self::assertTrue($result->success);
    self::assertSame('challenge-token-123', $result->challengeToken);
    self::assertSame('j***e@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
  }

  #[Test]
  public function testNormalizesEmailToLowercaseBeforeLookup(): void
  {
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->expects(self::once())
      ->method('findByEmail')
      ->with(self::callback(
        static fn (Email $email): bool => self::USER_EMAIL === $email->value,
      ))
      ->willReturn(null);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('generate');

    $handler = new RequestPasswordResetHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordResetCommand(email: 'JDoe@Example.COM'));

    self::assertTrue($result->success);
  }
  // #endregion

  // #region Helpers
  private function makeActiveUser(): User
  {
    $user = $this->makePendingUser();
    $user->verifyEmail(new TestEventIdProvider());
    $user->activate();

    return $user;
  }

  private function makePendingUser(): User
  {
    return User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email(self::USER_EMAIL),
      password: HashedPassword::fromPlain(self::USER_PASSWORD),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
  }
  // #endregion
}
