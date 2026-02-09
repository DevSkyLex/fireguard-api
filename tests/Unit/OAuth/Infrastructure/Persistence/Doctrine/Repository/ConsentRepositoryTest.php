<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use OAuth\Domain\ValueObject\Consent\ConsentId;
use OAuth\Infrastructure\Persistence\Doctrine\Record\ConsentRecord;
use OAuth\Infrastructure\Persistence\Doctrine\Repository\ConsentRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConsentRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: ConsentRepository::class)]
final class ConsentRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFindByIdReturnsNullWhenMissing(): void
  {
    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with('123e4567-e89b-12d3-a456-426614174000')
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(ConsentRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new ConsentRepository(entityManager: $entityManager);

    self::assertNull($repository->findById(new ConsentId('123e4567-e89b-12d3-a456-426614174000')));
  }
  // #endregion
}
