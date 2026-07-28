<?php

declare(strict_types=1);

namespace Tests\Integration\Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Repository\ChecklistRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_map;

/**
 * Test ChecklistRepositoryIntegrationTest.
 *
 * Executes `countItemsGroupedByChecklistId()` against a real database
 * (L1.10b). The unit-level counterpart mocks the QueryBuilder, so it asserts
 * the shape of the calls but never parses the resulting DQL — which is
 * exactly how a reserved-keyword join alias (`member`, colliding with
 * `MEMBER OF`) once shipped and produced a 500 elsewhere in this session.
 * This test exists to execute the grouped query for real, and to pin the
 * organization-scoping and zero-items invariants.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistRepository::class)]
final class ChecklistRepositoryIntegrationTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655471001';

  private const string OTHER_ORGANIZATION_ID = '770e8400-e29b-41d4-a716-446655471002';

  private const string CHECKLIST_WITH_ITEMS_ID = '770e8400-e29b-41d4-a716-446655471101';

  private const string CHECKLIST_WITHOUT_ITEMS_ID = '770e8400-e29b-41d4-a716-446655471102';

  private const string FOREIGN_CHECKLIST_ID = '770e8400-e29b-41d4-a716-446655471103';

  private EntityManagerInterface $entityManager;

  private ChecklistRepository $repository;

  protected function setUp(): void
  {
    self::bootKernel();

    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->repository = new ChecklistRepository($this->entityManager);

    $this->entityManager->persist($this->createOrganization(self::ORGANIZATION_ID, 'Checklist Count Org', 'checklist-count-org'));
    $this->entityManager->persist($this->createOrganization(self::OTHER_ORGANIZATION_ID, 'Checklist Count Org B', 'checklist-count-org-b'));
    $this->entityManager->flush();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testCountItemsGroupedByChecklistIdExcludesForeignOrganizationAndOmitsZeroItemChecklists(): void
  {
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Extinguisher Checklist',
      version: 'v1.0',
      items: [
        ChecklistItem::create(id: 'item-1', label: 'Check pressure gauge', position: 0),
        ChecklistItem::create(id: 'item-2', label: 'Check hose integrity', position: 1),
      ],
    ));

    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITHOUT_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Empty Checklist',
      version: 'v1.0',
    ));

    // Belongs to a DIFFERENT organization but shares the same requested ID
    // list below — must never contribute to the primary organization's map,
    // even though the caller explicitly asked about its ID.
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::FOREIGN_CHECKLIST_ID),
      organizationId: ChecklistOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      name: 'Foreign Org Checklist',
      version: 'v1.0',
      items: [
        ChecklistItem::create(id: 'foreign-item-1', label: 'Foreign item 1', position: 0),
        ChecklistItem::create(id: 'foreign-item-2', label: 'Foreign item 2', position: 1),
        ChecklistItem::create(id: 'foreign-item-3', label: 'Foreign item 3', position: 2),
        ChecklistItem::create(id: 'foreign-item-4', label: 'Foreign item 4', position: 3),
        ChecklistItem::create(id: 'foreign-item-5', label: 'Foreign item 5', position: 4),
      ],
    ));

    $counts = $this->repository->countItemsGroupedByChecklistId(
      ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      [self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID, self::FOREIGN_CHECKLIST_ID],
    );

    self::assertSame(
      [self::CHECKLIST_WITH_ITEMS_ID => 2],
      $counts,
      'Only the in-organization checklist with items must appear; the zero-item and foreign-org checklists must be absent.',
    );

    $foreignOrganizationCounts = $this->repository->countItemsGroupedByChecklistId(
      ChecklistOrganizationId::fromString(self::OTHER_ORGANIZATION_ID),
      [self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID, self::FOREIGN_CHECKLIST_ID],
    );

    self::assertSame([self::FOREIGN_CHECKLIST_ID => 5], $foreignOrganizationCounts);
  }

  #[Test]
  public function testCountItemsGroupedByChecklistIdReturnsEmptyArrayWhenNoChecklistIdsRequested(): void
  {
    $counts = $this->repository->countItemsGroupedByChecklistId(
      ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      [],
    );

    self::assertSame([], $counts);
  }

  #[Test]
  public function testFindByOrganizationIdDoesNotHydrateItems(): void
  {
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Extinguisher Checklist',
      version: 'v1.0',
      items: [
        ChecklistItem::create(id: 'item-1', label: 'Check pressure gauge', position: 0),
      ],
    ));

    $checklists = $this->repository->findByOrganizationId(
      ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    self::assertCount(1, $checklists);
    // L1.10b: the list query path must never hydrate items, not even a
    // single one — item counting goes exclusively through
    // countItemsGroupedByChecklistId().
    self::assertSame([], $checklists[0]->items());
  }

  #[Test]
  public function testSaveUpsertsItemsUpdatingAddingAndRemovingThem(): void
  {
    $checklist = Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Extinguisher Checklist',
      version: 'v1.0',
      items: [
        ChecklistItem::create(id: 'item-1', label: 'Check pressure gauge', position: 0),
        ChecklistItem::create(id: 'item-2', label: 'Check hose integrity', position: 1),
      ],
    );
    $this->repository->save($checklist);

    // item-1 is kept but relabelled, item-2 disappears, item-3 is brand new.
    $checklist->update(
      name: 'Revised Extinguisher Checklist',
      hasName: true,
      items: [
        ChecklistItem::create(id: 'item-1', label: 'Check pressure gauge (revised)', position: 0, required: false, description: 'Look at the needle'),
        ChecklistItem::create(id: 'item-3', label: 'Check pin seal', position: 1),
      ],
      hasItems: true,
    );
    $this->repository->save($checklist);
    $this->entityManager->clear();

    $reloaded = $this->repository->findById(ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID));

    self::assertInstanceOf(Checklist::class, $reloaded);
    self::assertSame('Revised Extinguisher Checklist', $reloaded->name());
    self::assertSame(
      ['item-1', 'item-3'],
      array_map(static fn (ChecklistItem $item): string => $item->id(), $reloaded->items()),
    );
    self::assertSame('Check pressure gauge (revised)', $reloaded->items()[0]->label());
    self::assertFalse($reloaded->items()[0]->required());
    self::assertSame('Look at the needle', $reloaded->items()[0]->description());
  }

  #[Test]
  public function testFindByIdReturnsNullForAnUnknownChecklist(): void
  {
    self::assertNull($this->repository->findById(ChecklistId::fromString(self::CHECKLIST_WITHOUT_ITEMS_ID)));
  }

  #[Test]
  public function testFindNamesByIdsResolvesNamesAndShortCircuitsOnAnEmptyList(): void
  {
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Extinguisher Checklist',
      version: 'v1.0',
    ));

    self::assertSame([], $this->repository->findNamesByIds([]));
    self::assertSame(
      [self::CHECKLIST_WITH_ITEMS_ID => 'Annual Extinguisher Checklist'],
      $this->repository->findNamesByIds([self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID]),
    );
  }

  #[Test]
  public function testFindAndCountByOrganizationIdApplyStatusAndSearchFilters(): void
  {
    $active = Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Sprinkler 100% survey_A',
      version: 'v1.0',
    );
    $this->repository->save($active);

    $archived = Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITHOUT_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Retired Checklist',
      version: 'v0.9',
    );
    $archived->archive();
    $this->repository->save($archived);
    $this->entityManager->clear();

    $organizationId = ChecklistOrganizationId::fromString(self::ORGANIZATION_ID);

    $archivedOnly = $this->repository->findByOrganizationId($organizationId, status: 'archived');
    self::assertSame(
      [self::CHECKLIST_WITHOUT_ITEMS_ID],
      array_map(static fn (Checklist $item): string => (string) $item->id(), $archivedOnly),
    );
    self::assertSame(1, $this->repository->countByOrganizationId($organizationId, status: 'archived'));

    // The search term carries LIKE metacharacters (`%` and `_`) that must be
    // escaped rather than behaving as wildcards.
    $searched = $this->repository->findByOrganizationId($organizationId, search: '100% survey_A');
    self::assertSame(
      [self::CHECKLIST_WITH_ITEMS_ID],
      array_map(static fn (Checklist $item): string => (string) $item->id(), $searched),
    );
    self::assertSame(1, $this->repository->countByOrganizationId($organizationId, search: '100% survey_A'));
    self::assertSame(0, $this->repository->countByOrganizationId($organizationId, search: '100_ survey%A'));
  }

  #[Test]
  public function testFindByOrganizationIdHonoursEveryWhitelistedSortFieldAndPaginates(): void
  {
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITH_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Bravo checklist',
      version: 'v2.0',
    ));
    $this->repository->save(Checklist::create(
      id: ChecklistId::fromString(self::CHECKLIST_WITHOUT_ITEMS_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Alpha checklist',
      version: 'v1.0',
    ));
    $this->entityManager->clear();

    $organizationId = ChecklistOrganizationId::fromString(self::ORGANIZATION_ID);

    foreach (['name', 'version', 'status', 'createdAt', 'unknownField'] as $field) {
      $ascending = $this->repository->findByOrganizationId(
        $organizationId,
        sorting: new Sorting($field, SortDirection::ASC),
      );

      self::assertCount(2, $ascending, 'Sorting by ' . $field . ' must not change the result set.');
    }

    $byName = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('name', SortDirection::ASC),
    );
    self::assertSame(
      ['Alpha checklist', 'Bravo checklist'],
      array_map(static fn (Checklist $item): string => $item->name(), $byName),
    );

    $secondPage = $this->repository->findByOrganizationId(
      $organizationId,
      sorting: new Sorting('name', SortDirection::ASC),
      limit: 1,
      offset: 1,
    );
    self::assertSame(
      ['Bravo checklist'],
      array_map(static fn (Checklist $item): string => $item->name(), $secondPage),
    );
  }

  private function createOrganization(string $id, string $name, string $slug): OrganizationRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = $name;
    $organization->slug = $slug;
    $organization->ownerUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->createdByUserId = '770e8400-e29b-41d4-a716-446655479000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;

    return $organization;
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM checklist_items WHERE checklist_id IN (:checklistIds)',
      ['checklistIds' => [self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID, self::FOREIGN_CHECKLIST_ID]],
      ['checklistIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM checklists WHERE id IN (:checklistIds)',
      ['checklistIds' => [self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID, self::FOREIGN_CHECKLIST_ID]],
      ['checklistIds' => ArrayParameterType::STRING],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id IN (:organizationIds)',
      ['organizationIds' => [self::ORGANIZATION_ID, self::OTHER_ORGANIZATION_ID]],
      ['organizationIds' => ArrayParameterType::STRING],
    );
    $this->entityManager->clear();
  }
}
