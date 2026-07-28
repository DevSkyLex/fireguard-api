<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{
  FacilityCoordinates,
  FacilityId,
  FacilityName,
  FacilityOrganizationId,
  FacilityStatus,
  FacilityType
};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use RuntimeException;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function ksort;

/**
 * Test FacilityRepositoryCoverage.
 *
 * Complements {@see FacilityRepositoryTest} by exercising the persistence,
 * lookup, aggregate-count and in-memory sorting paths of the repository that
 * the sibling suite does not touch (save upsert, findById/findPublishedById,
 * the dashboard count metrics, day bucketing, name resolution, and the
 * type/status/code/parent filter and sort branches).
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityRepository::class)]
final class FacilityRepositoryCoverageTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '660e8400-e29b-41d4-a716-446655443000';

  private const string ALPHA_ID = '660e8400-e29b-41d4-a716-446655443010';

  private const string BETA_ID = '660e8400-e29b-41d4-a716-446655443011';

  private const string GAMMA_ID = '660e8400-e29b-41d4-a716-446655443012';

  private const string DRAFT_ID = '660e8400-e29b-41d4-a716-446655443013';

  private const string SAVED_CHILD_ID = '660e8400-e29b-41d4-a716-446655443014';

  private const string SAVED_ROOT_ID = '660e8400-e29b-41d4-a716-446655443015';

  private const string MISSING_ID = '660e8400-e29b-41d4-a716-4466554430ff';

  private const string FOREIGN_ID = '660e8400-e29b-41d4-a716-4466554430fe';

  private EntityManagerInterface $entityManager;

  private FacilityRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    /** @var FacilityRepository $repository */
    $repository = static::getContainer()->get(FacilityRepository::class);
    $this->repository = $repository;

    $organization = $this->createOrganization();

    // Root site, active + published, with a code / address / coordinates.
    $alpha = $this->createFacility(
      id: self::ALPHA_ID,
      organization: $organization,
      parent: null,
      name: 'Alpha HQ',
      type: 'site',
      code: 'HQ-001',
      status: 'active',
      recordStatus: 'published',
      createdAt: new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
      address: '10 Rue de la Paix',
      latitude: 48.8566,
      longitude: 2.3522,
    );
    // Active + published building beneath Alpha.
    $this->createFacility(
      id: self::BETA_ID,
      organization: $organization,
      parent: $alpha,
      name: 'Beta Wing',
      type: 'building',
      code: 'BLD-002',
      status: 'active',
      recordStatus: 'published',
      createdAt: new DateTimeImmutable('2026-03-15T12:00:00+00:00'),
    );
    // Archived + published building beneath Alpha.
    $this->createFacility(
      id: self::GAMMA_ID,
      organization: $organization,
      parent: $alpha,
      name: 'Gamma Wing',
      type: 'building',
      code: null,
      status: 'archived',
      recordStatus: 'published',
      createdAt: new DateTimeImmutable('2026-03-20T12:00:00+00:00'),
    );
    // Draft intervention scratchpad: invisible to the published lookups.
    $this->createFacility(
      id: self::DRAFT_ID,
      organization: $organization,
      parent: null,
      name: 'Draft Scratchpad',
      type: 'zone',
      code: null,
      status: 'active',
      recordStatus: 'draft',
      createdAt: new DateTimeImmutable('2026-03-25T12:00:00+00:00'),
    );

    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSaveInsertsThenUpdatesAndMapsEveryField(): void
  {
    $child = Facility::create(
      id: FacilityId::fromString(self::SAVED_CHILD_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: FacilityType::BUILDING,
      name: new FacilityName('Delta Wing'),
      parentFacilityId: FacilityId::fromString(self::ALPHA_ID),
      code: 'DLT-004',
      address: '5 Avenue Foch',
      metadata: ['zone' => 'north'],
      coordinates: new FacilityCoordinates(48.8566, 2.3522),
    );
    // Insert path (the aggregate does not yet exist) with a non-null parent.
    $this->repository->save($child);
    $this->entityManager->clear();

    $loaded = $this->repository->findById(FacilityId::fromString(self::SAVED_CHILD_ID));
    self::assertInstanceOf(Facility::class, $loaded);
    self::assertSame('Delta Wing', (string) $loaded->name());
    self::assertSame('DLT-004', $loaded->code());
    self::assertSame('5 Avenue Foch', $loaded->address());
    self::assertSame(FacilityType::BUILDING, $loaded->type());
    self::assertSame(FacilityStatus::ACTIVE, $loaded->status());
    self::assertSame(self::ALPHA_ID, (string) $loaded->parentFacilityId());
    self::assertSame(['zone' => 'north'], $loaded->metadata());
    self::assertInstanceOf(FacilityCoordinates::class, $loaded->coordinates());
    self::assertEqualsWithDelta(48.8566, $loaded->coordinates()->latitude(), 1.0e-9);
    self::assertEqualsWithDelta(2.3522, $loaded->coordinates()->longitude(), 1.0e-9);

    // Update path (the aggregate already exists): mutate and persist again.
    $loaded->rename(new FacilityName('Delta Wing Renamed'));
    $loaded->changeCode('DLT-004B');
    $loaded->archive();
    $this->repository->save($loaded);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(FacilityId::fromString(self::SAVED_CHILD_ID));
    self::assertInstanceOf(Facility::class, $reloaded);
    self::assertSame('Delta Wing Renamed', (string) $reloaded->name());
    self::assertSame('DLT-004B', $reloaded->code());
    self::assertSame(FacilityStatus::ARCHIVED, $reloaded->status());
  }

  #[Test]
  public function testSavePersistsARootFacilityWithoutParent(): void
  {
    $root = Facility::create(
      id: FacilityId::fromString(self::SAVED_ROOT_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Epsilon Site'),
    );
    // Insert path with the null-parent branch.
    $this->repository->save($root);
    $this->entityManager->clear();

    $loaded = $this->repository->findById(FacilityId::fromString(self::SAVED_ROOT_ID));
    self::assertInstanceOf(Facility::class, $loaded);
    self::assertNull($loaded->parentFacilityId());
    self::assertNull($loaded->code());
    self::assertNull($loaded->coordinates());
    self::assertSame([], $loaded->metadata());
  }

  #[Test]
  public function testFindByIdAndFindPublishedByIdHandleDraftAndMissing(): void
  {
    // findById ignores record status: a draft scratchpad is still returned.
    self::assertInstanceOf(Facility::class, $this->repository->findById(FacilityId::fromString(self::DRAFT_ID)));
    // ... and a missing id yields null.
    self::assertNull($this->repository->findById(FacilityId::fromString(self::MISSING_ID)));

    // findPublishedById returns a published record ...
    $published = $this->repository->findPublishedById(FacilityId::fromString(self::ALPHA_ID));
    self::assertInstanceOf(Facility::class, $published);
    self::assertSame('Alpha HQ', (string) $published->name());
    self::assertInstanceOf(FacilityCoordinates::class, $published->coordinates());
    // ... but hides a draft record ...
    self::assertNull($this->repository->findPublishedById(FacilityId::fromString(self::DRAFT_ID)));
    // ... and a missing id.
    self::assertNull($this->repository->findPublishedById(FacilityId::fromString(self::MISSING_ID)));
  }

  #[Test]
  public function testCountMetricsExposeActiveOverviewAndTypeBreakdown(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    // countActiveByOrganizationId matches on status only (no record-status
    // filter): Alpha, Beta and the active Draft scratchpad all count; Gamma
    // is archived and excluded.
    self::assertSame(3, $this->repository->countActiveByOrganizationId($organizationId));

    // Overview spans every record (any status, any record status).
    self::assertSame(
      ['total' => 4, 'active' => 3],
      $this->repository->countOverviewByOrganizationId($organizationId),
    );
    // Overview narrowed to a single type.
    self::assertSame(
      ['total' => 2, 'active' => 1],
      $this->repository->countOverviewByOrganizationId($organizationId, 'building'),
    );

    // Type breakdown counts published records only; default excludes archived.
    // The repository groups by type without an explicit ORDER BY, so key
    // order is not guaranteed — sort both sides before comparing.
    $byType = $this->repository->countByTypeForOrganizationId($organizationId);
    ksort($byType);
    self::assertSame(['building' => 1, 'site' => 1], $byType);

    // includeArchived keeps the archived Gamma building.
    $byTypeWithArchived = $this->repository->countByTypeForOrganizationId($organizationId, includeArchived: true);
    ksort($byTypeWithArchived);
    self::assertSame(['building' => 2, 'site' => 1], $byTypeWithArchived);
  }

  #[Test]
  public function testCountByCreatedDayBucketsPublishedFacilitiesPerDay(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    $byDay = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-03-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
    );

    // The draft is excluded (published only); the three published facilities
    // land in their own day bucket.
    self::assertSame(1, $byDay['2026-03-10'] ?? null);
    self::assertSame(1, $byDay['2026-03-15'] ?? null);
    self::assertSame(1, $byDay['2026-03-20'] ?? null);
    self::assertArrayNotHasKey('2026-03-25', $byDay);

    // Narrowed to buildings: only Beta and Gamma remain.
    $buildingsByDay = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-03-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
      'building',
    );
    self::assertArrayNotHasKey('2026-03-10', $buildingsByDay);
    self::assertSame(1, $buildingsByDay['2026-03-15'] ?? null);
    self::assertSame(1, $buildingsByDay['2026-03-20'] ?? null);
  }

  #[Test]
  public function testGetFacilityNamesByIdsResolvesScopedNames(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    // Empty input short-circuits.
    self::assertSame([], $this->repository->getFacilityNamesByIds($organizationId, []));

    // Known ids resolve to their names; an id from no facility is dropped.
    $names = $this->repository->getFacilityNamesByIds(
      $organizationId,
      [self::ALPHA_ID, self::BETA_ID, self::FOREIGN_ID],
    );
    self::assertSame(
      [self::ALPHA_ID => 'Alpha HQ', self::BETA_ID => 'Beta Wing'],
      $names,
    );
  }

  #[Test]
  public function testFindByOrganizationIdAppliesTypeStatusCodeAndParentFilters(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    // Type filter (archived included so both buildings are visible).
    $buildings = $this->repository->findByOrganizationId(
      organizationId: $organizationId,
      includeArchived: true,
      type: 'building',
    );
    self::assertSame([self::BETA_ID, self::GAMMA_ID], $this->facilityIds($buildings));
    self::assertSame(2, $this->repository->countByOrganizationId(
      organizationId: $organizationId,
      includeArchived: true,
      type: 'building',
    ));

    // Explicit status filter (an explicit status disables the default active filter).
    $archived = $this->repository->findByOrganizationId(organizationId: $organizationId, status: 'archived');
    self::assertSame([self::GAMMA_ID], $this->facilityIds($archived));

    // Exact code filter.
    $byCode = $this->repository->findByOrganizationId(organizationId: $organizationId, code: 'HQ-001');
    self::assertSame([self::ALPHA_ID], $this->facilityIds($byCode));
    self::assertSame(1, $this->repository->countByOrganizationId(organizationId: $organizationId, code: 'HQ-001'));

    // Parent filter (non roots-only branch).
    $childrenOfAlpha = $this->repository->findByOrganizationId(
      organizationId: $organizationId,
      includeArchived: true,
      parentFacilityId: self::ALPHA_ID,
    );
    self::assertSame([self::BETA_ID, self::GAMMA_ID], $this->facilityIds($childrenOfAlpha));
    self::assertSame(2, $this->repository->countByOrganizationId(
      organizationId: $organizationId,
      includeArchived: true,
      parentFacilityId: self::ALPHA_ID,
    ));
  }

  #[Test]
  public function testFindByOrganizationIdSortsByRequestedField(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    // createdAt ascending / descending (Alpha 03-10, Beta 03-15, Gamma 03-20).
    self::assertSame(
      [self::ALPHA_ID, self::BETA_ID, self::GAMMA_ID],
      $this->facilityIds($this->repository->findByOrganizationId(
        organizationId: $organizationId,
        includeArchived: true,
        sorting: new Sorting('createdAt', SortDirection::ASC),
      )),
    );
    self::assertSame(
      [self::GAMMA_ID, self::BETA_ID, self::ALPHA_ID],
      $this->facilityIds($this->repository->findByOrganizationId(
        organizationId: $organizationId,
        includeArchived: true,
        sorting: new Sorting('createdAt', SortDirection::DESC),
      )),
    );

    // type ascending: the two buildings (id-tiebroken) precede the site.
    self::assertSame(
      [self::BETA_ID, self::GAMMA_ID, self::ALPHA_ID],
      $this->facilityIds($this->repository->findByOrganizationId(
        organizationId: $organizationId,
        includeArchived: true,
        sorting: new Sorting('type', SortDirection::ASC),
      )),
    );

    // The remaining sort fields exercise their resolveSortField arm; order is
    // database-dependent for nullable columns, so only completeness is asserted.
    foreach (['status', 'updatedAt', 'code'] as $field) {
      self::assertCount(3, $this->repository->findByOrganizationId(
        organizationId: $organizationId,
        includeArchived: true,
        sorting: new Sorting($field, SortDirection::ASC),
      ));
    }
  }

  #[Test]
  public function testFindDescendantsSortsInMemoryAndFiltersBySearch(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);
    $rootId = FacilityId::fromString(self::ALPHA_ID);

    // createdAt: Beta (03-15) before Gamma (03-20).
    self::assertSame(
      [self::BETA_ID, self::GAMMA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('createdAt', SortDirection::ASC),
      )),
    );
    self::assertSame(
      [self::GAMMA_ID, self::BETA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('createdAt', SortDirection::DESC),
      )),
    );

    // status: active (Beta) sorts before archived (Gamma).
    self::assertSame(
      [self::BETA_ID, self::GAMMA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('status', SortDirection::ASC),
      )),
    );

    // code: Gamma's null code (coalesced to '') sorts before Beta's 'BLD-002'.
    self::assertSame(
      [self::GAMMA_ID, self::BETA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('code', SortDirection::ASC),
      )),
    );

    // updatedAt descending mirrors createdAt here (updatedAt == createdAt).
    self::assertSame(
      [self::GAMMA_ID, self::BETA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('updatedAt', SortDirection::DESC),
      )),
    );

    // type ascending: both buildings, id tiebreak.
    self::assertSame(
      [self::BETA_ID, self::GAMMA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('type', SortDirection::ASC),
      )),
    );

    // Default field (name) ascending: 'Beta Wing' before 'Gamma Wing'.
    self::assertSame(
      [self::BETA_ID, self::GAMMA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        sorting: new Sorting('name', SortDirection::ASC),
      )),
    );

    // Search keeps only matching descendants ...
    self::assertSame(
      [self::GAMMA_ID],
      $this->facilityIds($this->repository->findDescendants(
        $organizationId,
        $rootId,
        includeArchived: true,
        search: 'Gamma',
      )),
    );
    // ... and drops everything when nothing matches.
    self::assertSame([], $this->repository->findDescendants(
      $organizationId,
      $rootId,
      includeArchived: true,
      search: 'no-such-facility',
    ));
  }

  #[Test]
  public function testCountByCreatedDayFallsBackToTheLowerBoundOffsetWhenNoTimeZoneIsGiven(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    // No explicit bucket time zone: the lower bound's own offset is used, so
    // the +02:00 bound pushes the 12:00 UTC rows into the local calendar day.
    $byDay = $this->repository->countByCreatedDayForOrganizationId(
      $organizationId,
      '2026-03-01T00:00:00+02:00',
      '2026-03-31T23:59:59+02:00',
    );

    self::assertSame(1, $byDay['2026-03-10'] ?? null);
    self::assertSame(1, $byDay['2026-03-15'] ?? null);
    self::assertSame(1, $byDay['2026-03-20'] ?? null);
  }

  #[Test]
  public function testCountByCreatedDayRejectsAnUnusableStorageTimeZone(): void
  {
    $repository = new FacilityRepository($this->entityManager, 'Nowhere/Nothing');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid DATABASE_STORAGE_TIMEZONE configuration.');

    $repository->countByCreatedDayForOrganizationId(
      FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      '2026-03-01T00:00:00+00:00',
      '2026-03-31T23:59:59+00:00',
      'UTC',
    );
  }

  #[Test]
  public function testCountChildrenByParentIdsShortCircuitsOnEmptyInputAndCountsPerParent(): void
  {
    $organizationId = FacilityOrganizationId::fromString(self::ORGANIZATION_ID);

    self::assertSame([], $this->repository->countChildrenByParentIds($organizationId, []));

    $alphaId = FacilityId::fromString(self::ALPHA_ID);

    // Active-only by default: Gamma is archived, so only Beta counts.
    self::assertSame(
      [self::ALPHA_ID => 1],
      $this->repository->countChildrenByParentIds($organizationId, [$alphaId]),
    );

    // Including archived children brings Gamma back in.
    self::assertSame(
      [self::ALPHA_ID => 2],
      $this->repository->countChildrenByParentIds($organizationId, [$alphaId], true),
    );

    // A parent with no children at all is simply absent from the map.
    self::assertSame(
      [],
      $this->repository->countChildrenByParentIds($organizationId, [FacilityId::fromString(self::BETA_ID)]),
    );
  }

  /**
   * @param list<Facility> $facilities
   *
   * @return list<string>
   */
  private function facilityIds(array $facilities): array
  {
    $ids = [];
    foreach ($facilities as $facility) {
      $ids[] = (string) $facility->id();
    }

    return $ids;
  }

  private function createOrganization(): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Facility Repository Coverage Test';
    $organization->slug = 'facility-repository-coverage-test';
    $organization->ownerUserId = '660e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = '660e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);

    return $organization;
  }

  private function createFacility(
    string $id,
    OrganizationRecord $organization,
    ?FacilityRecord $parent,
    string $name,
    string $type,
    ?string $code,
    string $status,
    string $recordStatus,
    DateTimeImmutable $createdAt,
    ?string $address = null,
    ?float $latitude = null,
    ?float $longitude = null,
  ): FacilityRecord {
    $facility = new FacilityRecord();
    $facility->id = $id;
    $facility->organization = $organization;
    $facility->parentFacility = $parent;
    $facility->type = $type;
    $facility->name = $name;
    $facility->code = $code;
    $facility->status = $status;
    $facility->recordStatus = $recordStatus;
    $facility->address = $address;
    $facility->latitude = $latitude;
    $facility->longitude = $longitude;
    $facility->metadata = [];
    $facility->createdAt = $createdAt;
    $facility->updatedAt = $createdAt;
    $this->entityManager->persist($facility);

    return $facility;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    // Self-referencing FK uses ON DELETE SET NULL, so a single scoped delete is safe.
    $connection->executeStatement(
      'DELETE FROM facilities WHERE organization_id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
