<?php

declare(strict_types=1);

namespace Tests\Integration\Session\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\SessionId;
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test SessionRepositoryIntegrationTest.
 *
 * @category Integration Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: SessionRepository::class)]
final class SessionRepositoryIntegrationTest extends KernelTestCase
{
  // #region Properties
  private EntityManagerInterface $entityManager;

  private SessionRepository $repository;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $this->entityManager = $entityManager;

    $this->repository = new SessionRepository(entityManager: $this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testSaveAndFindById(): void
  {
    $session = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174000',
      userId: 'user-1',
      accessTokenId: 'access-1',
      refreshTokenId: 'refresh-1',
    );

    $this->repository->save($session);

    $found = $this->repository->findById(new SessionId('123e4567-e89b-12d3-a456-426614174000'));

    self::assertNotNull($found);
    self::assertSame('user-1', $found->userId());
    self::assertSame('access-1', $found->accessTokenId());
  }

  #[Test]
  public function testSaveUpdatesExistingSession(): void
  {
    $session = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174010',
      userId: 'user-2',
      accessTokenId: 'access-old',
      refreshTokenId: 'refresh-old',
    );
    $this->repository->save($session);

    $session->updateTokens('access-new', 'refresh-new');
    $session->revoke();
    $this->repository->save($session);

    $found = $this->repository->findById(new SessionId('123e4567-e89b-12d3-a456-426614174010'));

    self::assertNotNull($found);
    self::assertSame('access-new', $found->accessTokenId());
    self::assertSame('refresh-new', $found->refreshTokenId());
    self::assertTrue($found->isRevoked());
  }

  #[Test]
  public function testFindByUserIdAndActiveByUserId(): void
  {
    $activeSession = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174020',
      userId: 'user-3',
      accessTokenId: 'access-3',
      refreshTokenId: 'refresh-3',
    );
    $revokedSession = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174021',
      userId: 'user-3',
      accessTokenId: 'access-4',
      refreshTokenId: 'refresh-4',
    );
    $revokedSession->revoke();

    $this->repository->save($activeSession);
    $this->repository->save($revokedSession);

    $all = $this->repository->findByUserId('user-3');
    $active = $this->repository->findActiveByUserId('user-3');

    self::assertCount(2, $all);
    self::assertCount(1, $active);
    self::assertSame($activeSession->id()->value, $active[0]->id()->value);
  }

  #[Test]
  public function testFindByAccessTokenIdAndRefreshTokenId(): void
  {
    $session = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174030',
      userId: 'user-4',
      accessTokenId: 'access-5',
      refreshTokenId: 'refresh-5',
    );

    $this->repository->save($session);

    $byAccess = $this->repository->findByAccessTokenId('access-5');
    $byRefresh = $this->repository->findByRefreshTokenId('refresh-5');

    self::assertNotNull($byAccess);
    self::assertNotNull($byRefresh);
    self::assertSame($session->id()->value, $byAccess->id()->value);
    self::assertSame($session->id()->value, $byRefresh->id()->value);
  }

  #[Test]
  public function testRevokeAllForUserRevokesSessions(): void
  {
    $sessionOne = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174040',
      userId: 'user-5',
      accessTokenId: 'access-6',
      refreshTokenId: 'refresh-6',
    );
    $sessionTwo = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174041',
      userId: 'user-5',
      accessTokenId: 'access-7',
      refreshTokenId: 'refresh-7',
    );

    $this->repository->save($sessionOne);
    $this->repository->save($sessionTwo);

    $count = $this->repository->revokeAllForUser('user-5');

    self::assertSame(2, $count);
    self::assertCount(0, $this->repository->findActiveByUserId('user-5'));
  }

  #[Test]
  public function testDeleteRemovesSession(): void
  {
    $session = $this->createSession(
      id: '123e4567-e89b-12d3-a456-426614174050',
      userId: 'user-6',
      accessTokenId: 'access-8',
      refreshTokenId: 'refresh-8',
    );

    $this->repository->save($session);

    $this->repository->delete(new SessionId('123e4567-e89b-12d3-a456-426614174050'));

    self::assertNull($this->repository->findById(new SessionId('123e4567-e89b-12d3-a456-426614174050')));
  }
  // #endregion

  // #region Helpers
  private function createSession(
    string $id,
    string $userId,
    ?string $accessTokenId = null,
    ?string $refreshTokenId = null,
  ): Session {
    return Session::create(
      id: new SessionId($id),
      userId: $userId,
      ipAddress: new IpAddress('127.0.0.1'),
      userAgent: new UserAgent('test-agent'),
      accessTokenId: $accessTokenId,
      refreshTokenId: $refreshTokenId,
    );
  }
  // #endregion
}
