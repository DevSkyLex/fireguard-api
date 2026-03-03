<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\ChecklistMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{ChecklistItemRecord, ChecklistRecord};

use function array_map;
use function in_array;

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
    $itemRecords = ChecklistMapper::toItemRecords($checklist);
    $existing = $this->checklistRepository->find($record->id);

    if ($existing instanceof ChecklistRecord) {
      $existing->organizationId = $record->organizationId;
      $existing->name = $record->name;
      $existing->version = $record->version;
      $existing->status = $record->status;
      $existing->updatedAt = $record->updatedAt;

      // Upsert items: update existing, add new, remove deleted
      $existingItems = $this->itemRepository->findBy(['checklistId' => $record->id]);
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
      ['checklistId' => (string) $id],
      ['position' => 'ASC'],
    );

    return ChecklistMapper::toDomain($record, $itemRecords);
  }

  public function findByOrganizationId(
    ChecklistOrganizationId $organizationId,
    ?string $status = null,
  ): array {
    $criteria = ['organizationId' => (string) $organizationId];

    if (null !== $status) {
      $criteria['status'] = $status;
    }

    $records = $this->checklistRepository->findBy($criteria, ['createdAt' => 'DESC']);

    return array_map(
      function (ChecklistRecord $record): Checklist {
        $itemRecords = $this->itemRepository->findBy(
          ['checklistId' => $record->id],
          ['position' => 'ASC'],
        );

        return ChecklistMapper::toDomain($record, $itemRecords);
      },
      $records,
    );
  }
}
