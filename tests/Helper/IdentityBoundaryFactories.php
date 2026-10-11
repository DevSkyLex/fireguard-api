<?php

declare(strict_types=1);

namespace Tests\Helper;

use Otp\Application\Port\Inbound\Challenge\OtpChallengePort;
use Otp\Application\Port\Outbound\Internal\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Application\Port\Outbound\Internal\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Application\Service\OtpChallengeService;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\GenerateOtpHandler;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpHandler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{EventBusPort, HashingPort, UuidGeneratorPort};
use Shared\Domain\ValueObject\Email;
use User\Application\Contract\User\UserView;
use User\Application\Port\Inbound\{UserCredentialsPort, UserDirectoryPort, UserRegistrationPort};
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\Service\{UserCredentialsService, UserDirectoryService, UserRegistrationService};
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserHandler, CreateUserResult};
use User\Domain\ValueObject\Username;

/**
 * Builds real owner capabilities around test persistence doubles.
 *
 * @category Test
 */
trait IdentityBoundaryFactories
{
  private function registrationBusCapability(UserRepositoryPort $repository, CommandBusPort $bus): UserRegistrationPort
  {
    return new class ($repository, $bus) implements UserRegistrationPort {
      public function __construct(private UserRepositoryPort $users, private CommandBusPort $bus)
      {
      }

      public function emailExists(string $email): bool
      {
        return $this->users->existsByEmail(new Email($email));
      }

      public function usernameExists(string $username): bool
      {
        return $this->users->existsByUsername(new Username($username));
      }

      public function register(string $username, string $email, string $password, string $firstName, string $lastName): string
      {
        /** @var CreateUserResult $result */
        $result = $this->bus->dispatch(new CreateUserCommand($username, $email, $password, $firstName, $lastName));
        \PHPUnit\Framework\Assert::assertInstanceOf(CreateUserResult::class, $result);

        return $result->userId;
      }

      public function confirm(string $userId, string $verifiedRecipient): ?UserView
      {
        return null;
      }
    };
  }

  private function challengeCapability(OtpRepositoryPort $repository): OtpChallengePort
  {
    return new OtpChallengeService(
      new GenerateOtpHandler($repository, $this->createStub(OtpNotifierPort::class), $this->createStub(UuidGeneratorPort::class)),
      new VerifyOtpHandler($repository, $this->createStub(TotpEnrollmentRepositoryPort::class), $this->createStub(TotpServicePort::class)),
    );
  }

  private function directoryCapability(UserRepositoryPort $repository): UserDirectoryPort
  {
    return new UserDirectoryService($repository);
  }

  private function credentialCapability(UserRepositoryPort $repository): UserCredentialsPort
  {
    return new UserCredentialsService($repository, $this->directoryCapability($repository));
  }

  private function registrationCapability(UserRepositoryPort $repository, ?EventBusPort $events = null): UserRegistrationPort
  {
    $events ??= $this->createStub(EventBusPort::class);
    $ids = new TestEventIdProvider();

    return new UserRegistrationService(
      $repository,
      $this->directoryCapability($repository),
      new CreateUserHandler($repository, $this->createStub(UuidGeneratorPort::class), $this->createStub(HashingPort::class), $events, $ids),
      $ids,
      $events,
    );
  }
}
