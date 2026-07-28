<?php

declare(strict_types=1);

namespace Tests\Integration\Organization\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Organization\Infrastructure\Persistence\Doctrine\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;
use function count;

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

  #[Test]
  public function testSavePersistsNewAggregateThenUpdatesTheExistingRecord(): void
  {
    $id = OrganizationId::fromString('b2000000-0000-4000-8000-000000000051');

    $this->repository->save(OrganizationMapper::toDomain($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000051',
      name: 'Saved Org',
      slug: 'saved-org',
    )));

    $persisted = $this->repository->findById($id);
    self::assertNotNull($persisted);
    self::assertSame('Saved Org', (string) $persisted->name());
    self::assertSame(OrganizationStatus::ACTIVE, $persisted->status());

    // A second save on the same identifier must take the "existing record"
    // branch and mutate the managed row rather than persisting a duplicate.
    $this->repository->save(OrganizationMapper::toDomain($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000051',
      name: 'Renamed Org',
      slug: 'renamed-org',
      status: OrganizationStatus::ARCHIVED->value,
    )));

    $updated = $this->repository->findById($id);
    self::assertNotNull($updated);
    self::assertSame('Renamed Org', (string) $updated->name());
    self::assertSame('renamed-org', (string) $updated->slug());
    self::assertSame(OrganizationStatus::ARCHIVED, $updated->status());
    self::assertFalse($updated->isActive());
  }

  #[Test]
  public function testEmptyIdentifierListShortCircuitsBeforeQuerying(): void
  {
    self::assertSame([], $this->repository->findByIds([]));
    self::assertSame(0, $this->repository->countByIds([]));
  }

  #[Test]
  public function testFindAllReturnsEveryPersistedOrganization(): void
  {
    // A delta, not an absolute: the seeded baseline already holds rows.
    $before = count($this->repository->findAll());

    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000061',
      name: 'All Listed Org',
      slug: 'all-listed-org',
    ));
    $this->entityManager->flush();

    $all = $this->repository->findAll();
    $slugs = array_map(static fn (Organization $organization): string => (string) $organization->slug(), $all);

    self::assertCount($before + 1, $all);
    self::assertContains('all-listed-org', $slugs);
  }

  #[Test]
  public function testFindByIdsSupportsEverySortableFieldAndPagination(): void
  {
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000071',
      name: 'Sortable Alpha',
      slug: 'sortable-alpha',
    ));
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000072',
      name: 'Sortable Bravo',
      slug: 'sortable-bravo',
    ));
    $this->entityManager->persist($this->createOrganization(
      id: 'b2000000-0000-4000-8000-000000000073',
      name: 'Sortable Charlie',
      slug: 'sortable-charlie',
    ));
    $this->entityManager->flush();

    $ids = [
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000071'),
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000072'),
      OrganizationId::fromString('b2000000-0000-4000-8000-000000000073'),
    ];

    // Every branch of the sort-field whitelist, plus the unknown-field default.
    foreach (['slug', 'status', 'createdAt', 'name', 'unmapped-field'] as $field) {
      self::assertCount(
        3,
        $this->repository->findByIds($ids, sorting: new Sorting($field, SortDirection::DESC)),
        'Sorting by "' . $field . '" must stay a valid query.',
      );
    }

    $descendingBySlug = array_map(
      static fn (Organization $organization): string => (string) $organization->slug(),
      $this->repository->findByIds($ids, sorting: new Sorting('slug', SortDirection::DESC)),
    );

    self::assertSame(['sortable-charlie', 'sortable-bravo', 'sortable-alpha'], $descendingBySlug);

    $secondPage = $this->repository->findByIds(
      $ids,
      sorting: new Sorting('slug', SortDirection::ASC),
      limit: 1,
      offset: 1,
    );

    self::assertCount(1, $secondPage);
    self::assertSame('sortable-bravo', (string) $secondPage[0]->slug());
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
