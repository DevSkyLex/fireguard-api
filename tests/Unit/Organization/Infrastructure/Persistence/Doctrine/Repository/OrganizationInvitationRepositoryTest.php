<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Domain\ValueObject\OrganizationInvitationId;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationInvitationRecord, OrganizationInvitationRoleRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationInvitationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationInvitationRepository::class)]
final class OrganizationInvitationRepositoryTest extends TestCase
{
  #[Test]
  public function testFindRoleIdsForInvitationReturnsEmptyArrayWhenInvitationIsUnknown(): void
  {
    $invitationId = '550e8400-e29b-41d4-a716-446655440801';

    $invitationRepository = $this->createMock(EntityRepository::class);
    $invitationRepository->expects(self::once())
      ->method('find')
      ->with($invitationId)
      ->willReturn(null);

    $invitationRoleRepository = $this->createMock(EntityRepository::class);
    $invitationRoleRepository->expects(self::never())
      ->method('findBy');

    $roleRepository = $this->createStub(EntityRepository::class);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::exactly(3))
      ->method('getRepository')
      ->willReturnMap([
        [OrganizationInvitationRecord::class, $invitationRepository],
        [OrganizationInvitationRoleRecord::class, $invitationRoleRepository],
        [OrganizationRoleRecord::class, $roleRepository],
      ]);

    $repository = new OrganizationInvitationRepository($entityManager);

    self::assertSame(
      [],
      $repository->findRoleIdsForInvitation(OrganizationInvitationId::fromString($invitationId)),
    );
  }
}
