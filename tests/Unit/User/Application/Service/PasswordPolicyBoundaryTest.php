<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\Service;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventBusPort, HashingPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\HashedSecret;
use Tests\Helper\TestEventIdProvider;
use User\Application\Port\Inbound\UserDirectoryPort;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;
use User\Application\Service\{FederatedUserService, UserCredentialsService};
use User\Application\UseCase\Command\User\CreateUser\{CreateUserCommand, CreateUserHandler};
use User\Domain\Model\User\User;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

/**
 * Class PasswordPolicyBoundaryTest
 *
 * Proves non-HTTP mutation paths enforce policy before persistence, hashing or identity allocation.
 *
 * @category Service Tests
 */
#[CoversClass(CreateUserHandler::class)]
#[CoversClass(UserCredentialsService::class)]
#[CoversClass(FederatedUserService::class)]
final class PasswordPolicyBoundaryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testCreateRejectsWeakPasswordBeforeAnyMutation(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->expects(self::never())->method('save');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::never())->method('generate');
    $hashing = $this->createMock(HashingPort::class);
    $hashing->expects(self::never())->method('hash');
    $events = $this->createMock(EventBusPort::class);
    $events->expects(self::never())->method('publish');
    $handler = new CreateUserHandler($users, $ids, $hashing, $events, new TestEventIdProvider(), new ConfiguredPasswordPolicyAdapter());

    $this->expectException(InvalidValueException::class);
    $handler(new CreateUserCommand('reviewer', 'reviewer@example.com', 'weak', 'Review', 'User'));
  }

  #[Test]
  public function testPasswordChangeUsesConfiguredRuleBeforeLookup(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->expects(self::never())->method('findById');
    $users->expects(self::never())->method('save');
    $service = new UserCredentialsService($users, $this->createStub(UserDirectoryPort::class), new ConfiguredPasswordPolicyAdapter(12, 128));

    $this->expectException(InvalidValueException::class);
    $service->changePassword('00000000-0000-4000-8000-000000000001', 'Abcdef1!');
  }

  #[Test]
  public function testFederatedInitialPasswordUsesActualProjectRuleBeforeHashing(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->expects(self::never())->method('findById');
    $users->expects(self::never())->method('save');
    $hashing = $this->createMock(HashingPort::class);
    $hashing->expects(self::never())->method('hash');
    $service = new FederatedUserService($users, $this->createStub(UuidGeneratorPort::class), $hashing, $this->createStub(EventBusPort::class), new TestEventIdProvider(), new ConfiguredPasswordPolicyAdapter());

    $this->expectException(InvalidValueException::class);
    $service->setInitialPassword('00000000-0000-4000-8000-000000000001', 'weak');
  }

  #[Test]
  public function testCustomPasswordAndOptionalVerificationAreAcceptedWithoutMailboxProof(): void
  {
    $users = $this->createMock(UserRepositoryPort::class);
    $users->expects(self::once())->method('save')->with(self::callback(static function (User $user): bool {
      return $user->canLogin() && !$user->isEmailVerified() && null === $user->emailOwnershipVerifiedAt();
    }));
    $ids = $this->createStub(UuidGeneratorPort::class);
    $ids->method('generate')->willReturn('00000000-0000-4000-8000-000000000001');
    $hashing = $this->createMock(HashingPort::class);
    $hashing->expects(self::once())->method('hash')->with('abcd')->willReturn(new HashedSecret('$2y$10$hashedpassword'));
    $handler = new CreateUserHandler($users, $ids, $hashing, $this->createStub(EventBusPort::class), new TestEventIdProvider(), new ConfiguredPasswordPolicyAdapter(4, 12, false, false, false, ''));
    $result = $handler(new CreateUserCommand('reviewer', 'reviewer@example.com', 'abcd', 'Review', 'User', requireEmailVerification: false));
    self::assertSame('00000000-0000-4000-8000-000000000001', $result->userId);
  }
  // #endregion
}
