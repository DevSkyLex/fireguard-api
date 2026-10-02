<?php

declare(strict_types=1);

namespace Tests\Integration\User\Infrastructure\Adapter\User;

use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Inbound\Totp\TotpEnrollmentPurgePort;
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
    $purge = $container->get(TotpEnrollmentPurgePort::class);
    self::assertInstanceOf(TotpEnrollmentPurgePort::class, $purge);
    $this->adapter = new UserDataPurgeAdapter($this->entityManager, $purge);
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

  #[Test]
  public function testPurgeRemovesAllTotpSecretSlotsAndPreservesTheOtherAccount(): void
  {
    $connection = $this->entityManager->getConnection();
    $targetId = '8a2d3b01-5e3f-4b2c-9d40-000000000205';
    $otherId = '8a2d3b01-5e3f-4b2c-9d40-000000000206';
    foreach ([$targetId, $otherId] as $userId) {
      $connection->insert('totp_enrollments', [
        'user_id' => $userId, 'active_secret' => 'TEST-ACTIVE-LEGACY', 'pending_secret' => 'TEST-PENDING-LEGACY',
        'active_secret_ciphertext' => 'synthetic-active-envelope', 'pending_secret_ciphertext' => 'synthetic-pending-envelope',
        'secrets_encrypted' => 1, 'attempts' => 0, 'max_attempts' => 5, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
      ]);
    }
    self::assertSame($targetId, $connection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$targetId]));
    $this->adapter->purgeForUser($targetId);
    self::assertFalse($connection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$targetId]));
    self::assertSame($otherId, $connection->fetchOne('SELECT user_id FROM totp_enrollments WHERE user_id = ?', [$otherId]));
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
