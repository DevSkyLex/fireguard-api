<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\PasswordChange;

use Auth\Application\UseCase\Command\PasswordChange\RequestPasswordChange\{
  RequestPasswordChangeCommand,
  RequestPasswordChangeHandler,
  RequestPasswordChangeResult
};
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};

#[CoversClass(RequestPasswordChangeHandler::class)]
final class RequestPasswordChangeHandlerTest extends TestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-000000000001';

  private const string CURRENT_PASSWORD = 'CurrentP@ssw0rd!';

  // #region Methods
  #[Test]
  public function testFailsWhenUserNotFound(): void
  {
    $repo = $this->createStub(UserRepositoryPort::class);
    $repo->method('findById')->willReturn(null);

    $handler = new RequestPasswordChangeHandler(
      userRepository: $repo,
      otpChallenge: $this->createStub(OtpChallengePort::class),
    );

    $result = $handler(new RequestPasswordChangeCommand(
      userId: self::USER_ID,
      currentPassword: self::CURRENT_PASSWORD,
    ));

    self::assertFalse($result->success);
    self::assertSame(RequestPasswordChangeResult::ERROR_USER_NOT_FOUND, $result->errorCode);
  }

  #[Test]
  public function testFailsWhenCurrentPasswordIsIncorrect(): void
  {
    $user = $this->makeActiveUser();

    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn($user);
    // The failed attempt must be persisted (lockout counter)
    $repo->expects(self::once())->method('save')->with($user);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::never())->method('generate');

    $handler = new RequestPasswordChangeHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordChangeCommand(
      userId: self::USER_ID,
      currentPassword: 'WrongP@ssw0rd!',
    ));

    self::assertFalse($result->success);
    self::assertSame(RequestPasswordChangeResult::ERROR_INVALID_PASSWORD, $result->errorCode);
  }

  #[Test]
  public function testGeneratesOtpChallengeWhenPasswordIsCorrect(): void
  {
    $user = $this->makeActiveUser();
    $expiresAt = new DateTimeImmutable('+15 minutes');

    /** @var UserRepositoryPort&MockObject $repo */
    $repo = $this->createMock(UserRepositoryPort::class);
    $repo->method('findById')->willReturn($user);
    $repo->expects(self::once())->method('save')->with($user);

    /** @var OtpChallengePort&MockObject $otp */
    $otp = $this->createMock(OtpChallengePort::class);
    $otp->expects(self::once())
      ->method('generate')
      ->with(
        self::USER_ID,
        OtpPurpose::SENSITIVE_OPERATION,
        self::anything(),
        'jdoe@example.com',
      )
      ->willReturn(new ChallengeInfo(
        challengeToken: 'challenge-token-123',
        maskedRecipient: 'j***e@example.com',
        expiresAt: $expiresAt,
        maxAttempts: 5,
      ));

    $handler = new RequestPasswordChangeHandler(
      userRepository: $repo,
      otpChallenge: $otp,
    );

    $result = $handler(new RequestPasswordChangeCommand(
      userId: self::USER_ID,
      currentPassword: self::CURRENT_PASSWORD,
    ));

    self::assertTrue($result->success);
    self::assertSame('challenge-token-123', $result->challengeToken);
    self::assertSame('j***e@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
  }
  // #endregion

  // #region Helpers
  private function makeActiveUser(): User
  {
    $user = User::register(
      id: new UserId(self::USER_ID),
      username: new Username('jdoe'),
      email: new Email('jdoe@example.com'),
      password: HashedPassword::fromPlain(self::CURRENT_PASSWORD),
      profile: new UserProfile('John', 'Doe'),
      eventIdProvider: new TestEventIdProvider(),
    );
    $user->verifyEmail(new TestEventIdProvider());
    $user->activate();

    return $user;
  }
  // #endregion
}
