<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\ChecklistMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

use function array_map;
use function in_array;
use function str_replace;

final readonly class ChecklistRepository implements ChecklistRepositoryPort
{
  // #region Constants
  private const string SEARCH_PLACEHOLDER = ':search';
  // #endregion

  /**
   * @var EntityRepository<ChecklistRecord>
   */
  private EntityRepository $checklistRepository;

  /**
   * @var EntityRepository<ChecklistItemRecord>
   */
  private EntityRepository $itemRepository;

  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->checklistRepository = $this->entityManager->getRepository(ChecklistRecord::class);
    $this->itemRepository = $this->entityManager->getRepository(ChecklistItemRecord::class);
  }

  public function save(Checklist $checklist): void
  {
    $record = ChecklistMapper::toRecord($checklist);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $checklist->organizationId());
    $record->organization = $organization;
    if (null !== $checklist->previousChecklistId()) {
      $record->previousChecklist = $this->entityManager->getReference(ChecklistRecord::class, (string) $checklist->previousChecklistId());
    }
    $itemRecords = ChecklistMapper::toItemRecords($checklist);
    $existing = $this->checklistRepository->find($record->id);

    if ($existing instanceof ChecklistRecord) {
      $this->updateExistingRecord($existing, $record, $organization, $itemRecords);
    } else {
      $this->insertRecord($record, $itemRecords);
    }

    $this->entityManager->flush();
  }

  public function findById(ChecklistId $id): ?Checklist
  {
    $record = $this->checklistRepository->find((string) $id);

    if (!$record instanceof ChecklistRecord) {
      return null;
    }

    $this->entityManager->refresh($record);

    /** @var list<ChecklistItemRecord> $itemRecords */
    $itemRecords = $this->itemRepository->createQueryBuilder('item')
      ->where('item.checklist = :checklist')
      ->setParameter('checklist', $record)
      ->orderBy('item.position', 'ASC')
      ->getQuery()
      ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
      ->getResult();

    return ChecklistMapper::toDomain($record, $itemRecords);
  }

  public function findByOrganizationId(
    ChecklistOrganizationId $organizationId,
    ?string $status = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::DESC),
    int $limit = 20,
    int $offset = 0,
  ): array {
    $qb = $this->createListQueryBuilder($organizationId, $status, $search);
    // Unique tiebreaker: the sort field above is caller-chosen and rarely
    // unique, so rows tied on it order arbitrarily and LIMIT/OFFSET then
    // repeats some across pages while skipping others.
    $qb->orderBy('c.' . $this->resolveSortField($sorting->field), $sorting->direction->value)
      ->addOrderBy('c.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<ChecklistRecord> $records */
    $records = $qb->getQuery()->getResult();

    // L1.10b: the list path never hydrates items — neither eagerly (no
    // fetch-join above) nor via a per-row query here. Callers needing item
    // counts must use countItemsGroupedByChecklistId(); a lazy `items`
    // collection touched inside a loop would be an N+1.
    return array_map(
      static fn (ChecklistRecord $record): Checklist => ChecklistMapper::toDomain($record, []),
      $records,
    );
  }

  public function countByOrganizationId(
    ChecklistOrganizationId $organizationId,
    ?string $status = null,
    ?string $search = null,
  ): int {
    $qb = $this->createListQueryBuilder($organizationId, $status, $search);
    $qb->select('COUNT(c.id)');

    return (int) $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * Method findNamesByIds.
   *
   * Single query resolving checklist display names, so a caller rendering a
   * page of inspections asks once rather than once per row.
   *
   * @param list<string> $checklistIds
   *
   * @return array<string, string>
   */
  public function findNamesByIds(array $checklistIds): array
  {
    if ([] === $checklistIds) {
      return [];
    }

    /** @var list<array{id: string, name: string}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('checklist.id AS id', 'checklist.name AS name')
      ->from(ChecklistRecord::class, 'checklist')
      ->where('checklist.id IN (:checklistIds)')
      ->setParameter('checklistIds', $checklistIds)
      ->getQuery()
      ->getArrayResult();

    $names = [];
    foreach ($rows as $row) {
      $names[$row['id']] = $row['name'];
    }

    return $names;
  }

  /**
   * Method countItemsGroupedByChecklistId.
   *
   * Single grouped query over `checklist_items` joined to `checklists`,
   * scoped to the organization and to the requested checklist IDs. Mirrors
   * `OrganizationMemberRepository::countActiveMembersGroupedByRoleId()`.
   *
   * @param list<string> $checklistIds
   *
   * @return array<string, int>
   */
  public function countItemsGroupedByChecklistId(
    ChecklistOrganizationId $organizationId,
    array $checklistIds,
  ): array {
    if ([] === $checklistIds) {
      return [];
    }

    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    /** @var list<array{checklistId: string|null, itemCount: int|string|null}> $rows */
    $rows = $this->itemRepository
      ->createQueryBuilder('checklistItem')
      ->select('IDENTITY(checklistItem.checklist) AS checklistId')
      ->addSelect('COUNT(checklistItem.id) AS itemCount')
      ->innerJoin('checklistItem.checklist', 'checklistRecord')
      ->where('checklistRecord.organization = :organization')
      ->andWhere('IDENTITY(checklistItem.checklist) IN (:checklistIds)')
      ->groupBy('checklistItem.checklist')
      ->setParameter('organization', $organization)
      ->setParameter('checklistIds', $checklistIds)
      ->getQuery()
      ->getScalarResult();

    $counts = [];
    foreach ($rows as $row) {
      $checklistId = (string) ($row['checklistId'] ?? '');
      if ('' === $checklistId) {
        continue;
      }

      $counts[$checklistId] = (int) ($row['itemCount'] ?? 0);
    }

    return $counts;
  }

  /**
   * @param list<string> $checklistIds
   *
   * @return list<string>
   */
  public function referencedIds(ChecklistOrganizationId $organizationId, array $checklistIds): array
  {
    if ([] === $checklistIds) {
      return [];
    }

    // Draft inspections already contain evidence; publication status never relaxes immutability.
    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT DISTINCT checklist_id FROM inspections WHERE organization_id = :organization AND checklist_id IN (:ids)',
      ['organization' => (string) $organizationId, 'ids' => $checklistIds],
      ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
    );
  }

  /**
   * @param list<ChecklistItemRecord> $itemRecords
   */
  private function updateExistingRecord(ChecklistRecord $existing, ChecklistRecord $record, OrganizationRecord $organization, array $itemRecords): void
  {
    $existing->organization = $organization;
    $existing->name = $record->name;
    $existing->referenceCode = $record->referenceCode;
    $existing->version = $record->version;
    $existing->status = $record->status;
    $existing->updatedAt = $record->updatedAt;

    // Upsert items: update existing, add new, remove deleted.
    $existingItems = $this->itemRepository->findBy(['checklist' => $existing]);
    /** @var array<string, ChecklistItemRecord> $existingById */
    $existingById = [];
    foreach ($existingItems as $existingItem) {
      $existingById[$existingItem->id] = $existingItem;
    }

    $newIds = [];
    foreach ($itemRecords as $itemRecord) {
      $newIds[] = $itemRecord->id;
      if (isset($existingById[$itemRecord->id])) {
        $existingById[$itemRecord->id]->label = $itemRecord->label;
        $existingById[$itemRecord->id]->position = $itemRecord->position;
        $existingById[$itemRecord->id]->required = $itemRecord->required;
        $existingById[$itemRecord->id]->description = $itemRecord->description;
      } else {
        $itemRecord->checklist = $existing;
        $this->entityManager->persist($itemRecord);
      }
    }

    foreach ($existingItems as $existingItem) {
      if (!in_array($existingItem->id, $newIds, true)) {
        $this->entityManager->remove($existingItem);
      }
    }
  }

  /**
   * @param list<ChecklistItemRecord> $itemRecords
   */
  private function insertRecord(ChecklistRecord $record, array $itemRecords): void
  {
    $this->entityManager->persist($record);
    foreach ($itemRecords as $itemRecord) {
      $itemRecord->checklist = $record;
      $this->entityManager->persist($itemRecord);
    }
  }

  private function createListQueryBuilder(
    ChecklistOrganizationId $organizationId,
    ?string $status,
    ?string $search,
  ): QueryBuilder {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('c')
      ->from(ChecklistRecord::class, 'c')
      ->andWhere('c.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $status) {
      $qb->andWhere('c.status = :status')->setParameter('status', $status);
    }

    if (null !== $search && '' !== $search) {
      $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
      $qb->andWhere($qb->expr()->orX(
        $qb->expr()->like('c.name', self::SEARCH_PLACEHOLDER),
        $qb->expr()->like('c.version', self::SEARCH_PLACEHOLDER),
        $qb->expr()->like('c.status', self::SEARCH_PLACEHOLDER),
      ))->setParameter('search', '%' . $escaped . '%');
    }

    return $qb;
  }

  private function resolveSortField(string $field): string
  {
    return match ($field) {
      'name' => 'name',
      'version' => 'version',
      'status' => 'status',
      default => 'createdAt',
    };
  }
}
