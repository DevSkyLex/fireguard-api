<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Command\Registration\RegisterUser;

use Auth\Application\UseCase\Command\Registration\RegisterUser\{
  RegisterUserCommand,
  RegisterUserHandler,
  RegisterUserResult
};
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{ChallengeInfo, OtpChannel, OtpPurpose};
use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Service\ChallengeResendPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserResult};

/**
 * Test RegisterUserHandlerTest.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class RegisterUserHandlerTest extends TestCase
{
  private const string USER_ID = '11111111-1111-4111-8111-111111111111';

  #[Test]
  public function itCreatesTheUserAndIssuesAnEmailVerificationChallenge(): void
  {
    $expiresAt = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
    $challenge = new ChallengeInfo('chal-token', 'n***@example.com', $expiresAt, 5);

    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('existsByEmail')->willReturn(false);
    $users->method('existsByUsername')->willReturn(false);

    $capturedUsername = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(
        function (CreateUserCommand $command) use (&$capturedUsername): CreateUserResult {
          $capturedUsername = $command->username;

          return new CreateUserResult(self::USER_ID);
        },
      );

    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::once())
      ->method('generate')
      ->willReturnCallback(
        function (
          string $userId,
          OtpPurpose $purpose,
          OtpChannel $channel,
          string $recipient,
        ) use ($challenge): ChallengeInfo {
          self::assertSame(self::USER_ID, $userId);
          self::assertSame(OtpPurpose::EMAIL_VERIFICATION, $purpose);
          self::assertSame(OtpChannel::EMAIL, $channel);
          // The email is lowercased before the challenge is issued.
          self::assertSame('new.user@example.com', $recipient);

          return $challenge;
        },
      );

    $handler = new RegisterUserHandler($commandBus, $users, $otpChallenge);

    $result = $handler(new RegisterUserCommand(
      email: 'New.User@Example.com',
      password: 'S3cur3-Passw0rd!',
      firstName: 'New',
      lastName: 'User',
    ));

    self::assertTrue($result->success);
    self::assertNull($result->errorCode);
    self::assertSame('chal-token', $result->challengeToken);
    self::assertSame('n***@example.com', $result->maskedRecipient);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame(5, $result->maxAttempts);
    self::assertSame(ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS, $result->canResendIn);
    self::assertSame('newuser', $capturedUsername);
  }

  #[Test]
  public function itReportsWhenTheEmailIsAlreadyRegistered(): void
  {
    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('existsByEmail')->willReturn(true);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::never())->method('generate');

    $handler = new RegisterUserHandler($commandBus, $users, $otpChallenge);

    $result = $handler(new RegisterUserCommand(
      email: 'Taken@Example.com',
      password: 'S3cur3-Passw0rd!',
      firstName: 'Al',
      lastName: 'Ready',
    ));

    self::assertFalse($result->success);
    self::assertSame(RegisterUserResult::ERROR_EMAIL_TAKEN, $result->errorCode);
    self::assertSame('An account already exists with this email address.', $result->message);
    self::assertNull($result->challengeToken);
    self::assertNull($result->expiresAt);
  }

  #[Test]
  public function itAppendsANumericSuffixWhenTheDerivedUsernameIsTaken(): void
  {
    $challenge = new ChallengeInfo(
      'chal-token',
      'j***@example.com',
      new DateTimeImmutable('2026-07-24T12:00:00+00:00'),
      5,
    );

    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('existsByEmail')->willReturn(false);
    // The bare base is taken; the first suffixed candidate is free.
    $users->method('existsByUsername')->willReturnOnConsecutiveCalls(true, false);

    $capturedUsername = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(
        function (CreateUserCommand $command) use (&$capturedUsername): CreateUserResult {
          $capturedUsername = $command->username;

          return new CreateUserResult(self::USER_ID);
        },
      );

    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::once())->method('generate')->willReturn($challenge);

    $handler = new RegisterUserHandler($commandBus, $users, $otpChallenge);

    $result = $handler(new RegisterUserCommand(
      email: 'johndoe@example.com',
      password: 'S3cur3-Passw0rd!',
      firstName: 'John',
      lastName: 'Doe',
    ));

    self::assertTrue($result->success);
    self::assertNotSame('johndoe', $capturedUsername);
    self::assertMatchesRegularExpression('/^johndoe-\d{4,6}$/', (string) $capturedUsername);
  }

  #[Test]
  public function itFallsBackToARandomUsernameWhenEveryCandidateIsTaken(): void
  {
    $challenge = new ChallengeInfo(
      'chal-token',
      'j***@example.com',
      new DateTimeImmutable('2026-07-24T12:00:00+00:00'),
      5,
    );

    $users = $this->createStub(UserRepositoryPort::class);
    $users->method('existsByEmail')->willReturn(false);
    // Every derived candidate collides, forcing the random fallback.
    $users->method('existsByUsername')->willReturn(true);

    $capturedUsername = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(
        function (CreateUserCommand $command) use (&$capturedUsername): CreateUserResult {
          $capturedUsername = $command->username;

          return new CreateUserResult(self::USER_ID);
        },
      );

    $otpChallenge = $this->createMock(OtpChallengePort::class);
    $otpChallenge->expects(self::once())->method('generate')->willReturn($challenge);

    $handler = new RegisterUserHandler($commandBus, $users, $otpChallenge);

    $result = $handler(new RegisterUserCommand(
      email: 'jane@example.com',
      password: 'S3cur3-Passw0rd!',
      firstName: 'Jane',
      lastName: 'Roe',
    ));

    self::assertTrue($result->success);
    self::assertMatchesRegularExpression('/^user-\d{9}$/', (string) $capturedUsername);
  }
}
