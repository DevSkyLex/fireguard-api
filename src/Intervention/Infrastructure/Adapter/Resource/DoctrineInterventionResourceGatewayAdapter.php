<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Resource;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Resource\{
  InterventionAssignmentContext,
  InterventionEquipmentDraft,
  InterventionListMetrics,
  InterventionResourceAssignment,
  InterventionResourceSummary,
  InterventionValidationContext,
  InterventionWorkItemSummary
};
use Intervention\Application\Port\Outbound\{InterventionEquipmentDraftProviderPort, InterventionResourceGatewayPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\InterventionResourceNotFoundException;
use Intervention\Domain\ValueObject\InterventionResourceType;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function in_array;
use function iterator_to_array;

/**
 * Resource DoctrineInterventionResourceGatewayAdapter.
 *
 * @category Resource
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineInterventionResourceGatewayAdapter implements InterventionResourceGatewayPort
{
  /**
   * Property owners.
   *
   * @since 1.0.0
   *
   * @var list<InterventionResourceOwnerPort>
   */
  private array $owners;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param iterable<InterventionResourceOwnerPort> $owners
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    iterable $owners,
    private InterventionEquipmentDraftProviderPort $equipmentDraftProvider,
  ) {
    $this->owners = iterator_to_array($owners, false);
  }

  /**
   * Method interventionAssignmentContext.
   *
   * Executes the intervention assignment context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionAssignmentContext the intervention assignment context result
   */
  public function interventionAssignmentContext(string $interventionId): ?InterventionAssignmentContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord || !$intervention->organization instanceof OrganizationRecord) {
      return null;
    }

    return new InterventionAssignmentContext(
      $intervention->id,
      $intervention->organization->id,
      $intervention->status,
      $intervention->responsibleId,
      $intervention->participants,
    );
  }

  /**
   * Method interventionMutationContext.
   *
   * Loads and locks an intervention for a resource mutation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionAssignmentContext the intervention mutation context result
   */
  public function interventionMutationContext(string $interventionId): ?InterventionAssignmentContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId, LockMode::PESSIMISTIC_WRITE);
    if (!$intervention instanceof InterventionRecord || !$intervention->organization instanceof OrganizationRecord) {
      return null;
    }

    return new InterventionAssignmentContext(
      $intervention->id,
      $intervention->organization->id,
      $intervention->status,
      $intervention->responsibleId,
      $intervention->participants,
    );
  }

  /**
   * Method validationContext.
   *
   * Executes the validation context operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return ?InterventionValidationContext the validation context result
   */
  public function validationContext(string $interventionId): ?InterventionValidationContext
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord) {
      return null;
    }

    return new InterventionValidationContext(
      $intervention->type,
      $intervention->status,
      $intervention->siteId,
      $intervention->responsibleId,
    );
  }

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(InterventionResourceType $type, string $resourceId): bool
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
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(
    InterventionResourceType $type,
    string $resourceId,
    string $organizationId,
  ): bool {
    return $this->owner($type)->resourceBelongsToOrganization($resourceId, $organizationId);
  }

  /**
   * Method resourceInInterventionScope.
   *
   * Determines whether a canonical resource is targeted by an intervention work item.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the resource type
   * @param string $resourceId the resource id
   * @param string $interventionId the intervention id
   *
   * @return bool whether the resource belongs to the prepared intervention scope
   */
  public function resourceInInterventionScope(
    InterventionResourceType $type,
    string $resourceId,
    string $interventionId,
  ): bool {
    $target = '/api/' . match ($type) {
      InterventionResourceType::FACILITY => 'facilities',
      InterventionResourceType::EQUIPMENT => 'equipment',
      InterventionResourceType::INSPECTION => 'inspections',
    } . '/' . $resourceId;

    return 0 < $this->entityManager->getRepository(InterventionWorkItemRecord::class)->count([
      'intervention' => $this->entityManager->getReference(InterventionRecord::class, $interventionId),
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
   * @param InterventionResourceType $type the type value
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(InterventionResourceType $type, string $clientId): bool
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
   * @param InterventionResourceType $type the type value
   * @param string $resourceId the resource id value
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the assign result
   */
  public function assign(
    InterventionResourceType $type,
    string $resourceId,
    ?string $interventionId,
    ?string $clientId,
  ): InterventionResourceAssignment {
    $assignment = $this->owner($type)->assign($resourceId, $interventionId, $clientId);
    if (null !== $interventionId) {
      $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
      if ($intervention instanceof InterventionRecord) {
        ++$intervention->revision;
        $intervention->updatedAt = new DateTimeImmutable();
      }
    }

    $this->entityManager->flush();

    return $assignment;
  }

  /**
   * Method touchDraftIntervention.
   *
   * Executes the touch draft intervention operation.
   *
   * @since 1.0.0
   *
   * @param ?string $interventionId the intervention id value
   */
  public function touchDraftIntervention(?string $interventionId): void
  {
    if (null === $interventionId) {
      return;
    }
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord || !in_array($intervention->status, ['draft', 'planned', 'in_progress', 'changes_requested'], true)) {
      return;
    }
    ++$intervention->revision;
    $intervention->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
  }

  /**
   * Method summary.
   *
   * Executes the summary operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return InterventionResourceSummary the summary result
   */
  public function summary(string $interventionId): InterventionResourceSummary
  {
    return new InterventionResourceSummary(
      facilities: $this->owner(InterventionResourceType::FACILITY)->countForIntervention($interventionId),
      equipment: $this->owner(InterventionResourceType::EQUIPMENT)->countForIntervention($interventionId),
      inspections: $this->owner(InterventionResourceType::INSPECTION)->countForIntervention($interventionId),
    );
  }

  /**
   * Method workItemSummary.
   *
   * Executes the work item summary operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return InterventionWorkItemSummary the work item summary result
   */
  public function workItemSummary(string $interventionId): InterventionWorkItemSummary
  {
    return new InterventionWorkItemSummary(
      total: $this->countWorkItems($interventionId, []),
      requiredIncomplete: (int) $this->entityManager->createQueryBuilder()
        ->select('COUNT(w.id)')
        ->from(InterventionWorkItemRecord::class, 'w')
        ->where('w.intervention = :intervention')
        ->andWhere('w.required = true')
        ->andWhere('w.status NOT IN (:done)')
        ->setParameter('intervention', $this->entityManager->getReference(InterventionRecord::class, $interventionId))
        ->setParameter('done', ['completed', 'skipped'])
        ->getQuery()
        ->getSingleScalarResult(),
      skipped: $this->countWorkItems($interventionId, ['status' => 'skipped']),
      discovered: $this->countWorkItems($interventionId, ['source' => 'discovered']),
      completed: $this->countWorkItems($interventionId, ['status' => ['completed', 'skipped']]),
    );
  }

  /**
   * Method listMetrics.
   *
   * Aggregates the metrics required to render an intervention collection.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, InterventionListMetrics> metrics indexed by intervention id
   */
  public function listMetrics(array $interventionIds): array
  {
    if ([] === $interventionIds) {
      return [];
    }
    /** @var array<string, array{facilities: int, equipment: int, inspections: int, workItems: int, completedWorkItems: int, requiredIncomplete: int, proposedChanges: int, resourceBlockers: int}> $values */
    $values = [];
    foreach ($interventionIds as $interventionId) {
      $values[$interventionId] = [
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
      [InterventionResourceType::FACILITY, 'facilities'],
      [InterventionResourceType::EQUIPMENT, 'equipment'],
      [InterventionResourceType::INSPECTION, 'inspections'],
    ] as [$type, $field]) {
      $owner = $this->owner($type);
      foreach ($owner->countsForInterventions($interventionIds) as $interventionId => $count) {
        if (isset($values[$interventionId])) {
          $values[$interventionId][$field] = $count;
        }
      }
      foreach ($owner->blockerCountsForInterventions($interventionIds) as $interventionId => $count) {
        if (isset($values[$interventionId])) {
          $values[$interventionId]['resourceBlockers'] += $count;
        }
      }
    }

    /** @var list<array{interventionId: string, total: string|int, completed: string|int, requiredIncomplete: string|int}> $workItemRows */
    $workItemRows = $this->entityManager->createQueryBuilder()
      ->select(
        'IDENTITY(record.intervention) AS interventionId',
        'COUNT(record.id) AS total',
        "SUM(CASE WHEN record.status IN ('completed', 'skipped') THEN 1 ELSE 0 END) AS completed",
        "SUM(CASE WHEN record.required = true AND record.status NOT IN ('completed', 'skipped') THEN 1 ELSE 0 END) AS requiredIncomplete",
      )
      ->from(InterventionWorkItemRecord::class, 'record')
      ->where('IDENTITY(record.intervention) IN (:interventionIds)')
      ->setParameter('interventionIds', $interventionIds)
      ->groupBy('record.intervention')
      ->getQuery()
      ->getArrayResult();
    foreach ($workItemRows as $row) {
      if (isset($values[$row['interventionId']])) {
        $values[$row['interventionId']]['workItems'] = (int) $row['total'];
        $values[$row['interventionId']]['completedWorkItems'] = (int) $row['completed'];
        $values[$row['interventionId']]['requiredIncomplete'] = (int) $row['requiredIncomplete'];
      }
    }

    /** @var list<array{interventionId: string, total: string|int}> $changeRows */
    $changeRows = $this->entityManager->createQueryBuilder()
      ->select('IDENTITY(record.intervention) AS interventionId', 'COUNT(record.id) AS total')
      ->from(InterventionChangeRecord::class, 'record')
      ->where('IDENTITY(record.intervention) IN (:interventionIds)')
      ->andWhere('record.status = :proposed')
      ->setParameter('interventionIds', $interventionIds)
      ->setParameter('proposed', 'proposed')
      ->groupBy('record.intervention')
      ->getQuery()
      ->getArrayResult();
    foreach ($changeRows as $row) {
      if (isset($values[$row['interventionId']])) {
        $values[$row['interventionId']]['proposedChanges'] = (int) $row['total'];
      }
    }

    $metrics = [];
    foreach ($values as $interventionId => $value) {
      $metrics[$interventionId] = new InterventionListMetrics(...$value);
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
   * @param string $interventionId the intervention id value
   *
   * @return list<InterventionEquipmentDraft> the equipment drafts result
   */
  public function equipmentDrafts(string $interventionId): array
  {
    return $this->equipmentDraftProvider->equipmentDrafts($interventionId);
  }

  /**
   * Method countWorkItems.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param array<string, mixed> $criteria
   *
   * @return int the count work items result
   */
  private function countWorkItems(string $interventionId, array $criteria): int
  {
    return (int) $this->entityManager->getRepository(InterventionWorkItemRecord::class)->count([
      'intervention' => $this->entityManager->getReference(InterventionRecord::class, $interventionId),
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
   * @param InterventionResourceType $type the type value
   *
   * @return InterventionResourceOwnerPort the owner result
   */
  private function owner(InterventionResourceType $type): InterventionResourceOwnerPort
  {
    foreach ($this->owners as $owner) {
      if ($owner->supportsResourceType($type)) {
        return $owner;
      }
    }

    throw InterventionResourceNotFoundException::withId($type, 'owner');
  }
}
