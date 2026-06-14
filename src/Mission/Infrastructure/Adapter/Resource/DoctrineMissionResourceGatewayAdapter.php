<?php

declare(strict_types=1);

namespace Mission\Infrastructure\Adapter\Resource;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Mission\Application\Contract\Resource\{
  MissionAssignmentContext,
  MissionEquipmentDraft,
  MissionListMetrics,
  MissionResourceAssignment,
  MissionResourceSummary,
  MissionValidationContext,
  MissionWorkItemSummary
};
use Mission\Application\Port\Outbound\{MissionEquipmentDraftProviderPort, MissionResourceGatewayPort, MissionResourceOwnerPort};
use Mission\Domain\Exception\MissionResourceNotFoundException;
use Mission\Domain\ValueObject\MissionResourceType;
use Mission\Infrastructure\Persistence\Doctrine\Record\{MissionChangeRecord, MissionRecord, MissionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function in_array;
use function iterator_to_array;

/**
 * Resource DoctrineMissionResourceGatewayAdapter.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineMissionResourceGatewayAdapter implements MissionResourceGatewayPort
{
  /**
   * Property owners.
   *
   * @since 1.0.0
   *
   * @var list<MissionResourceOwnerPort>
   */
  private array $owners;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param iterable<MissionResourceOwnerPort> $owners
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    iterable $owners,
    private MissionEquipmentDraftProviderPort $equipmentDraftProvider,
  ) {
    $this->owners = iterator_to_array($owners, false);
  }

  /**
   * Method missionAssignmentContext.
   *
   * Executes the mission assignment context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionAssignmentContext the mission assignment context result
   */
  public function missionAssignmentContext(string $missionId): ?MissionAssignmentContext
  {
    $mission = $this->entityManager->find(MissionRecord::class, $missionId);
    if (!$mission instanceof MissionRecord || !$mission->organization instanceof OrganizationRecord) {
      return null;
    }

    return new MissionAssignmentContext(
      $mission->id,
      $mission->organization->id,
      $mission->status,
      $mission->responsibleId,
      $mission->participants,
    );
  }

  /**
   * Method missionMutationContext.
   *
   * Loads and locks a mission for a resource mutation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionAssignmentContext the mission mutation context result
   */
  public function missionMutationContext(string $missionId): ?MissionAssignmentContext
  {
    $mission = $this->entityManager->find(MissionRecord::class, $missionId, LockMode::PESSIMISTIC_WRITE);
    if (!$mission instanceof MissionRecord || !$mission->organization instanceof OrganizationRecord) {
      return null;
    }

    return new MissionAssignmentContext(
      $mission->id,
      $mission->organization->id,
      $mission->status,
      $mission->responsibleId,
      $mission->participants,
    );
  }

  /**
   * Method validationContext.
   *
   * Executes the validation context operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return ?MissionValidationContext the validation context result
   */
  public function validationContext(string $missionId): ?MissionValidationContext
  {
    $mission = $this->entityManager->find(MissionRecord::class, $missionId);
    if (!$mission instanceof MissionRecord) {
      return null;
    }

    return new MissionValidationContext(
      $mission->type,
      $mission->status,
      $mission->siteId,
      $mission->responsibleId,
    );
  }

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(MissionResourceType $type, string $resourceId): bool
  {
    return $this->owner($type)->resourceExists($resourceId);
  }

  /**
   * Method resourceBelongsToOrganization.
   *
   * Executes the resource belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(
    MissionResourceType $type,
    string $resourceId,
    string $organizationId,
  ): bool {
    return $this->owner($type)->resourceBelongsToOrganization($resourceId, $organizationId);
  }

  /**
   * Method resourceInMissionScope.
   *
   * Determines whether a canonical resource is targeted by a mission work item.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the resource type
   * @param string $resourceId the resource id
   * @param string $missionId the mission id
   *
   * @return bool whether the resource belongs to the prepared mission scope
   */
  public function resourceInMissionScope(
    MissionResourceType $type,
    string $resourceId,
    string $missionId,
  ): bool {
    $target = '/api/' . match ($type) {
      MissionResourceType::FACILITY => 'facilities',
      MissionResourceType::EQUIPMENT => 'equipment',
      MissionResourceType::INSPECTION => 'inspections',
    } . '/' . $resourceId;

    return 0 < $this->entityManager->getRepository(MissionWorkItemRecord::class)->count([
      'mission' => $this->entityManager->getReference(MissionRecord::class, $missionId),
      'target' => $target,
    ]);
  }

  /**
   * Method clientIdExists.
   *
   * Executes the client id exists operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(MissionResourceType $type, string $clientId): bool
  {
    return $this->owner($type)->clientIdExists($clientId);
  }

  /**
   * Method assign.
   *
   * Executes the assign operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param ?string $missionId the mission id value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the assign result
   */
  public function assign(
    MissionResourceType $type,
    string $resourceId,
    ?string $missionId,
    ?string $clientId,
  ): MissionResourceAssignment {
    $assignment = $this->owner($type)->assign($resourceId, $missionId, $clientId);
    if (null !== $missionId) {
      $mission = $this->entityManager->find(MissionRecord::class, $missionId);
      if ($mission instanceof MissionRecord) {
        ++$mission->revision;
        $mission->updatedAt = new DateTimeImmutable();
      }
    }

    $this->entityManager->flush();

    return $assignment;
  }

  /**
   * Method touchDraftMission.
   *
   * Executes the touch draft mission operation.
   *
   * @since 1.0.0
   *
   * @param ?string $missionId the mission id value
   */
  public function touchDraftMission(?string $missionId): void
  {
    if (null === $missionId) {
      return;
    }
    $mission = $this->entityManager->find(MissionRecord::class, $missionId);
    if (!$mission instanceof MissionRecord || !in_array($mission->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)) {
      return;
    }
    ++$mission->revision;
    $mission->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
  }

  /**
   * Method summary.
   *
   * Executes the summary operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return MissionResourceSummary the summary result
   */
  public function summary(string $missionId): MissionResourceSummary
  {
    return new MissionResourceSummary(
      facilities: $this->owner(MissionResourceType::FACILITY)->countForMission($missionId),
      equipment: $this->owner(MissionResourceType::EQUIPMENT)->countForMission($missionId),
      inspections: $this->owner(MissionResourceType::INSPECTION)->countForMission($missionId),
    );
  }

  /**
   * Method workItemSummary.
   *
   * Executes the work item summary operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return MissionWorkItemSummary the work item summary result
   */
  public function workItemSummary(string $missionId): MissionWorkItemSummary
  {
    return new MissionWorkItemSummary(
      total: $this->countWorkItems($missionId, []),
      requiredIncomplete: (int) $this->entityManager->createQueryBuilder()
        ->select('COUNT(w.id)')
        ->from(MissionWorkItemRecord::class, 'w')
        ->where('w.mission = :mission')
        ->andWhere('w.required = true')
        ->andWhere('w.status NOT IN (:done)')
        ->setParameter('mission', $this->entityManager->getReference(MissionRecord::class, $missionId))
        ->setParameter('done', ['completed', 'skipped'])
        ->getQuery()
        ->getSingleScalarResult(),
      skipped: $this->countWorkItems($missionId, ['status' => 'skipped']),
      discovered: $this->countWorkItems($missionId, ['source' => 'discovered']),
      completed: $this->countWorkItems($missionId, ['status' => ['completed', 'skipped']]),
    );
  }

  /**
   * Method listMetrics.
   *
   * Aggregates the metrics required to render a mission collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, MissionListMetrics> metrics indexed by mission id
   */
  public function listMetrics(array $missionIds): array
  {
    if ([] === $missionIds) {
      return [];
    }
    /** @var array<string, array{facilities: int, equipment: int, inspections: int, workItems: int, completedWorkItems: int, requiredIncomplete: int, proposedChanges: int, resourceBlockers: int}> $values */
    $values = [];
    foreach ($missionIds as $missionId) {
      $values[$missionId] = [
        'facilities' => 0,
        'equipment' => 0,
        'inspections' => 0,
        'workItems' => 0,
        'completedWorkItems' => 0,
        'requiredIncomplete' => 0,
        'proposedChanges' => 0,
        'resourceBlockers' => 0,
      ];
    }
    foreach ([
      [MissionResourceType::FACILITY, 'facilities'],
      [MissionResourceType::EQUIPMENT, 'equipment'],
      [MissionResourceType::INSPECTION, 'inspections'],
    ] as [$type, $field]) {
      $owner = $this->owner($type);
      foreach ($owner->countsForMissions($missionIds) as $missionId => $count) {
        if (isset($values[$missionId])) {
          $values[$missionId][$field] = $count;
        }
      }
      foreach ($owner->blockerCountsForMissions($missionIds) as $missionId => $count) {
        if (isset($values[$missionId])) {
          $values[$missionId]['resourceBlockers'] += $count;
        }
      }
    }

    /** @var list<array{missionId: string, total: string|int, completed: string|int, requiredIncomplete: string|int}> $workItemRows */
    $workItemRows = $this->entityManager->createQueryBuilder()
      ->select(
        'IDENTITY(record.mission) AS missionId',
        'COUNT(record.id) AS total',
        "SUM(CASE WHEN record.status IN ('completed', 'skipped') THEN 1 ELSE 0 END) AS completed",
        "SUM(CASE WHEN record.required = true AND record.status NOT IN ('completed', 'skipped') THEN 1 ELSE 0 END) AS requiredIncomplete",
      )
      ->from(MissionWorkItemRecord::class, 'record')
      ->where('IDENTITY(record.mission) IN (:missionIds)')
      ->setParameter('missionIds', $missionIds)
      ->groupBy('record.mission')
      ->getQuery()
      ->getArrayResult();
    foreach ($workItemRows as $row) {
      if (isset($values[$row['missionId']])) {
        $values[$row['missionId']]['workItems'] = (int) $row['total'];
        $values[$row['missionId']]['completedWorkItems'] = (int) $row['completed'];
        $values[$row['missionId']]['requiredIncomplete'] = (int) $row['requiredIncomplete'];
      }
    }

    /** @var list<array{missionId: string, total: string|int}> $changeRows */
    $changeRows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(record.mission) AS missionId', 'COUNT(record.id) AS total')
      ->from(MissionChangeRecord::class, 'record')
      ->where('IDENTITY(record.mission) IN (:missionIds)')
      ->andWhere('record.status = :proposed')
      ->setParameter('missionIds', $missionIds)
      ->setParameter('proposed', 'proposed')
      ->groupBy('record.mission')
      ->getQuery()
      ->getArrayResult();
    foreach ($changeRows as $row) {
      if (isset($values[$row['missionId']])) {
        $values[$row['missionId']]['proposedChanges'] = (int) $row['total'];
      }
    }

    $metrics = [];
    foreach ($values as $missionId => $value) {
      $metrics[$missionId] = new MissionListMetrics(...$value);
    }

    return $metrics;
  }

  /**
   * Method equipmentDrafts.
   *
   * Executes the equipment drafts operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return list<MissionEquipmentDraft> the equipment drafts result
   */
  public function equipmentDrafts(string $missionId): array
  {
    return $this->equipmentDraftProvider->equipmentDrafts($missionId);
  }

  /**
   * Method countWorkItems.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param array<string, mixed> $criteria
   *
   * @return int the count work items result
   */
  private function countWorkItems(string $missionId, array $criteria): int
  {
    return (int) $this->entityManager->getRepository(MissionWorkItemRecord::class)->count([
      'mission' => $this->entityManager->getReference(MissionRecord::class, $missionId),
      ...$criteria,
    ]);
  }

  /**
   * Method owner.
   *
   * Executes the owner operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   *
   * @return MissionResourceOwnerPort the owner result
   */
  private function owner(MissionResourceType $type): MissionResourceOwnerPort
  {
    foreach ($this->owners as $owner) {
      if ($owner->supportsResourceType($type)) {
        return $owner;
      }
    }

    throw MissionResourceNotFoundException::withId($type, 'owner');
  }
}
