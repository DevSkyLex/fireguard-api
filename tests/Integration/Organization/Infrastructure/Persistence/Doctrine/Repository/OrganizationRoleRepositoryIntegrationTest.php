<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationRecord, OrganizationRoleRecord};
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRoleRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test OrganizationRoleRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationRoleRepository::class)]
final class OrganizationRoleRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private OrganizationRoleRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new OrganizationRoleRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByOrganizationIdReturnsScopedRolesOrderedByName(): void
  {
    $organization = $this->createOrganization('c3000000-0000-4000-8000-000000000001', 'role-scope-org');
    $otherOrganization = $this->createOrganization('c3000000-0000-4000-8000-000000000002', 'role-scope-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000101', $organization, 'inspector'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000102', $organization, 'auditor'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000103', $otherOrganization, 'foreign_role'));
    $this->entityManager->flush();

    $roles = $this->repository->findByOrganizationId(OrganizationId::fromString('c3000000-0000-4000-8000-000000000001'));
    $names = array_map(static fn ($role): string => (string) $role->name(), $roles);

    self::assertSame(['auditor', 'inspector'], $names, 'Roles are scoped to the organization and ordered by name.');
  }

  #[Test]
  public function testCountByOrganizationIdAndCountSystemByOrganizationId(): void
  {
    $organization = $this->createOrganization('c3000000-0000-4000-8000-000000000011', 'role-count-org');
    $this->entityManager->persist($organization);

    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000201', $organization, 'custom_one'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000202', $organization, 'custom_two'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000203', $organization, 'admin', isSystem: true));
    $this->entityManager->flush();

    $id = OrganizationId::fromString('c3000000-0000-4000-8000-000000000011');

    self::assertSame(3, $this->repository->countByOrganizationId($id));
    self::assertSame(1, $this->repository->countSystemByOrganizationId($id));
  }

  #[Test]
  public function testFindByIdsInOrganizationScopesToOrganizationAndIds(): void
  {
    $organization = $this->createOrganization('c3000000-0000-4000-8000-000000000021', 'role-ids-org');
    $otherOrganization = $this->createOrganization('c3000000-0000-4000-8000-000000000022', 'role-ids-org-b');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($otherOrganization);

    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000301', $organization, 'wanted_role'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000302', $organization, 'unwanted_role'));
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000303', $otherOrganization, 'foreign_wanted'));
    $this->entityManager->flush();

    $roles = $this->repository->findByIdsInOrganization(
      OrganizationId::fromString('c3000000-0000-4000-8000-000000000021'),
      [
        OrganizationRoleId::fromString('c3000000-0000-4000-8000-000000000301'),
        // Belongs to another organization: must be filtered out by the scope.
        OrganizationRoleId::fromString('c3000000-0000-4000-8000-000000000303'),
      ],
    );

    self::assertCount(1, $roles);
    self::assertSame('c3000000-0000-4000-8000-000000000301', (string) $roles[0]->id());
    self::assertSame([], $this->repository->findByIdsInOrganization(
      OrganizationId::fromString('c3000000-0000-4000-8000-000000000021'),
      [],
    ));
  }

  #[Test]
  public function testFindByOrganizationAndNameReturnsMatchingRole(): void
  {
    $organization = $this->createOrganization('c3000000-0000-4000-8000-000000000031', 'role-name-org');
    $this->entityManager->persist($organization);
    $this->entityManager->persist($this->createRole('c3000000-0000-4000-8000-000000000401', $organization, 'inspector'));
    $this->entityManager->flush();

    $found = $this->repository->findByOrganizationAndName(
      OrganizationId::fromString('c3000000-0000-4000-8000-000000000031'),
      new OrganizationRoleName('inspector'),
    );
    $missing = $this->repository->findByOrganizationAndName(
      OrganizationId::fromString('c3000000-0000-4000-8000-000000000031'),
      new OrganizationRoleName('unknown_role'),
    );

    self::assertNotNull($found);
    self::assertSame('c3000000-0000-4000-8000-000000000401', (string) $found->id());
    self::assertNull($missing);
  }

  private function createOrganization(string $id, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Org ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'c3000000-0000-4000-8000-0000000000aa';
    $organization->createdByUserId = 'c3000000-0000-4000-8000-0000000000aa';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }

  private function createRole(string $id, OrganizationRecord $organization, string $name, bool $isSystem = false): OrganizationRoleRecord
  {
    $role = new OrganizationRoleRecord();
    $role->id = $id;
    $role->organization = $organization;
    $role->name = $name;
    $role->description = 'Role integration fixture';
    $role->permissions = ['organization.read'];
    $role->isSystem = $isSystem;
    $role->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $role;
  }
}
