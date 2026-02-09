<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Otp\Domain\ValueObject\OtpId;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\OtpMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use Otp\Infrastructure\Persistence\Doctrine\Repository\OtpRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: OtpRepository::class)]
final class OtpRepositoryTest extends TestCase
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
      ->with(OtpRecord::class)
      ->willReturn($doctrineRepository);

    $repository = new OtpRepository(
      entityManager: $entityManager,
      mapper: new OtpMapper(),
    );

    self::assertNull($repository->findById(new OtpId('123e4567-e89b-12d3-a456-426614174000')));
  }
  // #endregion
}
