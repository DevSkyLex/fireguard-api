<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Domain\ValueObject\OrganizationId;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationRepositoryTest.
 *
 * @category Repository Tests
 */
#[CoversClass(className: OrganizationRepository::class)]
final class OrganizationRepositoryTest extends TestCase
{
  #[Test]
  public function testDeleteRemovesOrganization(): void
  {
    $record = new OrganizationRecord();
    $record->id = '123e4567-e89b-12d3-a456-426614174000';

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('find')
      ->with($record->id)
      ->willReturn($record);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(OrganizationRecord::class)
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('remove')
      ->with($record);
    $entityManager->expects(self::once())
      ->method('flush');
    $entityManager->expects(self::never())
      ->method('clear');

    $repository = new OrganizationRepository(
      entityManager: $entityManager,
    );

    $repository->delete(OrganizationId::fromString($record->id));
  }
}
