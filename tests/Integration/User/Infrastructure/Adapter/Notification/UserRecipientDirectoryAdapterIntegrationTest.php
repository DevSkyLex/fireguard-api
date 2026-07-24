<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Adapter\Notification;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\{Email, TenantId};
use Shared\Infrastructure\Service\UuidEventIdProvider;
use Shared\Infrastructure\Symfony\Adapter\Outbound\UuidGeneratorAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Domain\Model\User\User;
use User\Domain\ValueObject\{HashedPassword, UserId, UserProfile, Username};
use User\Infrastructure\Adapter\Notification\UserRecipientDirectoryAdapter;
use User\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use User\Infrastructure\Persistence\Doctrine\Repository\UserRepository;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test UserRecipientDirectoryAdapterIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserRecipientDirectoryAdapter::class)]
final class UserRecipientDirectoryAdapterIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private UserRepository $repository;

  private UserRecipientDirectoryAdapter $adapter;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    /** @var UserMapper $mapper */
    $mapper = $container->get(UserMapper::class);
    $this->repository = new UserRepository($this->entityManager, $mapper);
    $this->adapter = new UserRecipientDirectoryAdapter($this->repository);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testEmailForUserIdReturnsEmailWhenUserExists(): void
  {
    $userId = '7f1c2a90-4d2e-4a1b-9c3f-000000000101';
    $this->repository->save(
      $this->createTestUser($userId, 'recipientuser1', 'recipient1@example.com'),
    );

    self::assertSame('recipient1@example.com', $this->adapter->emailForUserId($userId));
  }

  #[Test]
  public function testEmailForUserIdReturnsNullWhenUserDoesNotExist(): void
  {
    self::assertNull($this->adapter->emailForUserId('7f1c2a90-4d2e-4a1b-9c3f-0000000001ff'));
  }

  #[Test]
  public function testEmailForUserIdReturnsNullWhenIdentifierIsMalformed(): void
  {
    self::assertNull($this->adapter->emailForUserId('not-a-valid-uuid'));
  }
  // #endregion

  // #region Helpers
  private function createTestUser(string $id, string $username, string $email): User
  {
    $eventIdProvider = new UuidEventIdProvider(new UuidGeneratorAdapter());
    $user = User::register(
      id: new UserId($id),
      username: new Username($username),
      email: new Email($email),
      password: new HashedPassword(password_hash('password123', PASSWORD_BCRYPT)),
      profile: new UserProfile('Test', 'User'),
      tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
      eventIdProvider: $eventIdProvider,
    );
    $user->releaseEvents();

    return $user;
  }
  // #endregion
}
