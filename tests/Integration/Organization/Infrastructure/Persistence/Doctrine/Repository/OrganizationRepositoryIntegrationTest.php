<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test OrganizationRepository.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OrganizationRepository::class)]
final class OrganizationRepositoryIntegrationTest extends KernelTestCase
{
  private EntityManagerInterface $entityManager;

  private OrganizationRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;
    $this->repository = new OrganizationRepository($this->entityManager);
  }

  protected function tearDown(): void
  {
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testFindByIdReturnsMappedOrganizationAndNullWhenMissing(): void
  {
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000001',
      name: 'Acme Fire Safety',
      slug: 'acme-fire-safety',
    ));
    $this->entityManager->flush();

    $found = $this->repository->findById(OrganizationId::fromString('b2000000-0000-4000-8000-000000000001'));
    $missing = $this->repository->findById(OrganizationId::fromString('b2000000-0000-4000-8000-0000000000ff'));

    self::assertNotNull($found);
    self::assertSame('acme-fire-safety', (string) $found->slug());
    self::assertNull($missing);
  }

  #[Test]
  public function testFindByIdsScopesToProvidedIdsAndExcludesArchivedByDefault(): void
  {
    $active = $this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000011',
      name: 'Active Org',
      slug: 'active-org',
    );
    $archived = $this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000012',
      name: 'Archived Org',
      slug: 'archived-org',
      status: OrganizationStatus::ARCHIVED->value,
    );
    $outOfScope = $this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000013',
      name: 'Out Of Scope Org',
      slug: 'out-of-scope-org',
    );
    $this->entityManager->persist($active);
    $this->entityManager->persist($archived);
    $this->entityManager->persist($outOfScope);
    $this->entityManager->flush();

    $scopedIds = [
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000011'),
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000012'),
    ];

    $results = $this->repository->findByIds($scopedIds);
    $slugs = array_map(static fn ($organization): string => (string) $organization->slug(), $results);

    self::assertContains('active-org', $slugs);
    self::assertNotContains('archived-org', $slugs, 'Archived organizations are hidden from the default listing.');
    self::assertNotContains('out-of-scope-org', $slugs, 'Only requested identifiers are returned.');
  }

  #[Test]
  public function testFindByIdsWithExplicitStatusSurfacesArchivedAndCountByIdsMatches(): void
  {
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000021',
      name: 'Kept Org',
      slug: 'kept-org',
    ));
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000022',
      name: 'Dropped Org',
      slug: 'dropped-org',
      status: OrganizationStatus::ARCHIVED->value,
    ));
    $this->entityManager->flush();

    $ids = [
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000021'),
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000022'),
    ];

    $archivedResults = $this->repository->findByIds($ids, OrganizationStatus::ARCHIVED->value);
    $archivedCount = $this->repository->countByIds($ids, OrganizationStatus::ARCHIVED->value);

    self::assertCount(1, $archivedResults);
    self::assertSame('dropped-org', (string) $archivedResults[0]->slug());
    self::assertSame(1, $archivedCount);
  }

  #[Test]
  public function testCountByIdsAppliesSearchFilter(): void
  {
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000031',
      name: 'Brigade Alpha',
      slug: 'brigade-alpha',
    ));
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000032',
      name: 'Sentinel Beta',
      slug: 'sentinel-beta',
    ));
    $this->entityManager->flush();

    $ids = [
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000031'),
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000032'),
    ];

    self::assertSame(1, $this->repository->countByIds($ids, null, 'brigade'));
    self::assertSame(2, $this->repository->countByIds($ids));
  }

  #[Test]
  public function testDeleteRemovesOrganization(): void
  {
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000041',
      name: 'Temporary Org',
      slug: 'temporary-org',
    ));
    $this->entityManager->flush();

    $id = OrganizationId::fromString('b2000000-0000-4000-8000-000000000041');
    self::assertNotNull($this->repository->findById($id));

    $this->repository->delete($id);

    self::assertNull($this->repository->findById($id));
  }

  private function createOrganization(
    string $id,
    string $name,
    string $slug,
    string $status = 'active',
  ): OrganizationRecord {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = 'b2000000-0000-4000-8000-0000000000aa';
    $organization->createdByUserId = 'b2000000-0000-4000-8000-0000000000aa';
    $organization->status = $status;
    $organization->isActive = OrganizationStatus::ARCHIVED->value !== $status;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    return $organization;
  }
}
