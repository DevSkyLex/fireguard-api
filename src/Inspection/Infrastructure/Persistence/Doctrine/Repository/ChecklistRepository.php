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
    $itemRecords = ChecklistMapper::toItemRecords($checklist);
    $existing = $this->checklistRepository->find($record->id);

    if ($existing instanceof ChecklistRecord) {
      $existing->organization = $organization;
      $existing->name = $record->name;
      $existing->version = $record->version;
      $existing->status = $record->status;
      $existing->updatedAt = $record->updatedAt;

      // Upsert items: update existing, add new, remove deleted
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
    } else {
      $this->entityManager->persist($record);
      foreach ($itemRecords as $itemRecord) {
        $itemRecord->checklist = $record;
        $this->entityManager->persist($itemRecord);
      }
    }

    $this->entityManager->flush();
  }

  public function findById(ChecklistId $id): ?Checklist
  {
    $record = $this->checklistRepository->find((string) $id);

    if (!$record instanceof ChecklistRecord) {
      return null;
    }

    $itemRecords = $this->itemRepository->findBy(
      ['checklist' => $record],
      ['position' => 'ASC'],
    );

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
    $qb->orderBy('c.' . $this->resolveSortField($sorting->field), $sorting->direction->value)
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<ChecklistRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      function (ChecklistRecord $record): Checklist {
        $itemRecords = $this->itemRepository->findBy(
          ['checklist' => $record],
          ['position' => 'ASC'],
        );

        return ChecklistMapper::toDomain($record, $itemRecords);
      },
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
        $qb->expr()->like('c.name', ':search'),
        $qb->expr()->like('c.version', ':search'),
        $qb->expr()->like('c.status', ':search'),
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
