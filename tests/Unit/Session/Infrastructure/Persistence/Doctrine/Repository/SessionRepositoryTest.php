<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;

/**
 * Test SessionRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: SessionRepository::class)]
final class SessionRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFindByRefreshTokenIdReturnsNullWhenMissing(): void
  {
    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->with(['refreshTokenId' => 'refresh-token'])
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(SessionRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new SessionRepository(entityManager: $entityManager);

    self::assertNull($repository->findByRefreshTokenId('refresh-token'));
  }
  // #endregion
}
