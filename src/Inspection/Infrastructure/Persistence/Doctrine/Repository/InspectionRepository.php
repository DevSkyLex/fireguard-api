<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;

use function array_map;

final readonly class InspectionRepository implements InspectionRepositoryPort
{
  /**
   * @var EntityRepository<InspectionRecord>
   */
  private EntityRepository $repository;

  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(InspectionRecord::class);
  }

  public function save(Inspection $inspection): void
  {
    $record = InspectionMapper::toRecord($inspection);
    $existing = $this->repository->find($record->id);

    if ($existing instanceof InspectionRecord) {
      $existing->organizationId = $record->organizationId;
      $existing->equipmentId = $record->equipmentId;
      $existing->facilityId = $record->facilityId;
      $existing->inspectorType = $record->inspectorType;
      $existing->inspectorName = $record->inspectorName;
      $existing->inspectorUserId = $record->inspectorUserId;
      $existing->inspectorOrganizationName = $record->inspectorOrganizationName;
      $existing->result = $record->result;
      $existing->status = $record->status;
      $existing->performedAt = $record->performedAt;
      $existing->checklistId = $record->checklistId;
      $existing->notes = $record->notes;
      $existing->signature = $record->signature;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function findById(InspectionId $id): ?Inspection
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionRecord) {
      return null;
    }

    return InspectionMapper::toDomain($record);
  }

  public function findByOrganizationId(
    InspectionOrganizationId $organizationId,
    ?string $equipmentId = null,
    ?string $facilityId = null,
    ?string $result = null,
    ?string $status = null,
  ): array {
    $criteria = ['organizationId' => (string) $organizationId];

    if (null !== $equipmentId) {
      $criteria['equipmentId'] = $equipmentId;
    }
    if (null !== $facilityId) {
      $criteria['facilityId'] = $facilityId;
    }
    if (null !== $result) {
      $criteria['result'] = $result;
    }
    if (null !== $status) {
      $criteria['status'] = $status;
    }

    $records = $this->repository->findBy($criteria, ['createdAt' => 'DESC']);

    return array_map(
      static fn (InspectionRecord $record): Inspection => InspectionMapper::toDomain($record),
      $records,
    );
  }
}
