<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Adapter\User;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use User\Infrastructure\Adapter\User\UserDataPurgeAdapter;

/**
 * Test UserDataPurgeAdapterIntegrationTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UserDataPurgeAdapter::class)]
final class UserDataPurgeAdapterIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private SessionRepository $sessions;

  private UserDataPurgeAdapter $adapter;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.auth_entity_manager');
    $this->entityManager = $entityManager;

    $this->sessions = new SessionRepository(entityManager: $this->entityManager);
    $this->adapter = new UserDataPurgeAdapter($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testPurgeForUserRemovesUserSessions(): void
  {
    $userId = '8a2d3b01-5e3f-4b2c-9d40-000000000201';
    $this->sessions->save(
      $this->createSession('8a2d3b01-5e3f-4b2c-9d40-0000000002a1', $userId),
    );

    self::assertCount(1, $this->sessions->findByUserId($userId));

    $this->adapter->purgeForUser($userId);

    self::assertCount(0, $this->sessions->findByUserId($userId));
  }

  #[Test]
  public function testPurgeForUserOnlyAffectsTheTargetedUser(): void
  {
    $targetUserId = '8a2d3b01-5e3f-4b2c-9d40-000000000202';
    $otherUserId = '8a2d3b01-5e3f-4b2c-9d40-000000000203';

    $this->sessions->save(
      $this->createSession('8a2d3b01-5e3f-4b2c-9d40-0000000002b1', $targetUserId),
    );
    $this->sessions->save(
      $this->createSession('8a2d3b01-5e3f-4b2c-9d40-0000000002b2', $otherUserId),
    );

    $this->adapter->purgeForUser($targetUserId);

    self::assertCount(0, $this->sessions->findByUserId($targetUserId));
    self::assertCount(1, $this->sessions->findByUserId($otherUserId));
  }

  #[Test]
  public function testPurgeForUserWithBlankIdentifierIsANoOp(): void
  {
    $userId = '8a2d3b01-5e3f-4b2c-9d40-000000000204';
    $this->sessions->save(
      $this->createSession('8a2d3b01-5e3f-4b2c-9d40-0000000002c1', $userId),
    );

    $this->adapter->purgeForUser('   ');

    self::assertCount(1, $this->sessions->findByUserId($userId));
  }
  // #endregion

  // #region Helpers
  private function createSession(string $id, string $userId): Session
  {
    return Session::create(
      id: new SessionId($id),
      userId: $userId,
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('test-agent'),
      accessTokenId: null,
      refreshTokenId: null,
    );
  }
  // #endregion
}
