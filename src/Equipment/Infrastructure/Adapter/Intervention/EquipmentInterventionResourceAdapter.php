<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Intervention\Application\Contract\Resource\{InterventionEquipmentDraft, InterventionResourceAssignment};
use Intervention\Application\Port\Outbound\{InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionEquipmentDraftProviderPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_map;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Adapter EquipmentInterventionResourceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentInterventionResourceAdapter implements InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionEquipmentDraftProviderPort, InterventionResourceOwnerPort
{
  private const PATCHABLE_FIELDS = ['type', 'subType', 'brand', 'model', 'serialNumber', 'locationLabel', 'status', 'facility'];

  private const STATUSES = ['in_stock', 'operational', 'decommissioned', 'under_maintenance'];

  /**
   * Legal equipment status transitions, mirroring the `Equipment` aggregate.
   * `decommissioned` is terminal.
   *
   * @var array<string, list<string>>
   */
  private const array ALLOWED_STATUS_TRANSITIONS = [
    'in_stock' => ['operational', 'decommissioned'],
    'operational' => ['in_stock', 'under_maintenance', 'decommissioned'],
    'under_maintenance' => ['in_stock', 'operational', 'decommissioned'],
    'decommissioned' => [],
  ];

  /**
   * Constructor.
   *
   * Initializes a new instance of the EquipmentInterventionResourceAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param FacilityValidationPort $facilityValidation the facility validation value
   * @param EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer the maintenance-log synchronizer
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityValidationPort $facilityValidation,
    private EquipmentMaintenanceLogSynchronizerPort $maintenanceLogSynchronizer,
  ) {
  }

  /**
   * Method supports.
   *
   * Executes the supports operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   *
   * @return bool the supports result
   */
  public function supports(string $resource): bool
  {
    return 1 === preg_match('#^/api/equipment/[^/]+$#', $resource);
  }

  /**
   * Method supportsResourceType.
   *
   * Executes the supports resource type operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   *
   * @return bool the supports resource type result
   */
  public function supportsResourceType(InterventionResourceType $type): bool
  {
    return InterventionResourceType::EQUIPMENT === $type;
  }

  /**
   * Method resourceExists.
   *
   * Executes the resource exists operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   *
   * @return bool the resource exists result
   */
  public function resourceExists(string $resourceId): bool
  {
    return $this->entityManager->find(EquipmentRecord::class, $resourceId) instanceof EquipmentRecord;
  }

  /**
   * Method resourceBelongsToOrganization.
   *
   * Executes the resource belongs to organization operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   * @param string $organizationId the organization id value
   *
   * @return bool the resource belongs to organization result
   */
  public function resourceBelongsToOrganization(string $resourceId, string $organizationId): bool
  {
    $record = $this->entityManager->find(EquipmentRecord::class, $resourceId);

    return $record instanceof EquipmentRecord && $record->organization?->id === $organizationId;
  }

  /**
   * Method clientIdExists.
   *
   * Executes the client id exists operation.
   *
   * @since 1.0.0
   *
   * @param string $clientId the client id value
   *
   * @return bool the client id exists result
   */
  public function clientIdExists(string $clientId): bool
  {
    return null !== $this->entityManager->getRepository(EquipmentRecord::class)->findOneBy(['clientId' => $clientId]);
  }

  /**
   * Method assign.
   *
   * Executes the assign operation.
   *
   * @since 1.0.0
   *
   * @param string $resourceId the resource id value
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the assign result
   */
  public function assign(string $resourceId, ?string $interventionId, ?string $clientId): InterventionResourceAssignment
  {
    $record = $this->entityManager->find(EquipmentRecord::class, $resourceId);
    if (!$record instanceof EquipmentRecord) {
      throw InterventionResourceNotFoundException::withId(InterventionResourceType::EQUIPMENT, $resourceId);
    }
    $record->clientId = $clientId;
    $record->interventionId = $interventionId;
    $record->recordStatus = null === $interventionId ? 'published' : 'draft';
    $record->revision = 1;
    $this->entityManager->flush();

    return new InterventionResourceAssignment($interventionId, $record->recordStatus, $record->revision);
  }

  /**
   * Method countForIntervention.
   *
   * Executes the count for intervention operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return int the count for intervention result
   */
  public function countForIntervention(string $interventionId): int
  {
    return $this->entityManager->getRepository(EquipmentRecord::class)->count(['interventionId' => $interventionId]);
  }

  /**
   * Method countsForInterventions.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, int> counts indexed by intervention id
   */
  public function countsForInterventions(array $interventionIds): array
  {
    return $this->aggregateCounts($interventionIds, false);
  }

  /**
   * Method blockerCountsForInterventions.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   *
   * @return array<string, int> blocker counts indexed by intervention id
   */
  public function blockerCountsForInterventions(array $interventionIds): array
  {
    return $this->aggregateCounts($interventionIds, true);
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
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->getRepository(EquipmentRecord::class)->findBy(['interventionId' => $interventionId]);

    return array_map(
      static fn (EquipmentRecord $record): InterventionEquipmentDraft => new InterventionEquipmentDraft(
        $record->id,
        $record->facilityId,
        $record->serialNumber,
      ),
      $records,
    );
  }

  /**
   * Method apply.
   *
   * Executes the apply operation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $resource the resource value
   * @param array<string, mixed> $patch the patch value
   */
  public function apply(string $organizationId, string $resource, array $patch): void
  {
    $this->assertPatchFields($patch);
    $record = $this->entityManager->find(EquipmentRecord::class, $this->id($resource));
    if (!$record instanceof EquipmentRecord || $record->organization?->id !== $organizationId || 'published' !== $record->recordStatus) {
      throw new InterventionConflictException('Proposed equipment change target is invalid.');
    }
    $previousStatus = $record->status;

    if (array_key_exists('type', $patch)) {
      $type = $patch['type'];
      if (!is_string($type) || '' === $type) {
        throw new InterventionConflictException('Equipment type cannot be empty.');
      }
      $record->type = $type;
    }

    foreach (['subType', 'brand', 'model', 'serialNumber', 'locationLabel'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new InterventionConflictException(sprintf('Equipment field "%s" must be a string or null.', $property));
        }
        $record->{$property} = $value;
      }
    }

    if (array_key_exists('status', $patch)) {
      $status = $patch['status'];
      if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
        throw new InterventionConflictException('Proposed equipment status is invalid.');
      }
      $record->status = $status;
    }

    if (array_key_exists('facility', $patch)) {
      $facilityIri = $patch['facility'];
      if (null === $facilityIri) {
        $record->facilityId = null;
      } elseif (is_string($facilityIri)) {
        $facilityId = $this->resourceId($facilityIri, 'facilities');

        try {
          $this->facilityValidation->assertFacilityIsAssignable($facilityId, $organizationId);
        } catch (Throwable) {
          throw new InterventionConflictException('Proposed equipment facility is invalid.');
        }
        $record->facilityId = $facilityId;
      } else {
        throw new InterventionConflictException('Proposed equipment facility must be an IRI or null.');
      }
    }

    // In-service equipment (operational or under maintenance) requires a facility,
    // mirroring the aggregate: clearing it while in service would strand the asset in
    // an illegal state (and, for under_maintenance, leak its open maintenance log).
    if (in_array($record->status, ['operational', 'under_maintenance'], true) && null === $record->facilityId) {
      throw new InterventionConflictException('In-service equipment must be assigned to a facility.');
    }

    // A published equipment change follows the domain status machine even on the
    // intervention publication path: a decommissioned asset is terminal and no
    // illegal transition may be applied directly to the record.
    $statusChanged = $record->status !== $previousStatus;
    if ($statusChanged) {
      $this->assertLegalStatusTransition($previousStatus, $record->status);
      // Stamp the first commissioning date on entering service (preserved on
      // re-commission), mirroring Equipment::commission().
      if ('operational' === $record->status && 'operational' !== $previousStatus) {
        $record->commissionedAt ??= new DateTimeImmutable();
      }
    }

    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();

    if ($statusChanged) {
      // Keep the maintenance-log history in step with the transition (mirrors the
      // PutUnderMaintenance / Commission / Decommission handlers).
      $this->maintenanceLogSynchronizer->syncForStatusTransition(
        $record->id,
        $organizationId,
        $previousStatus,
        $record->status,
      );
    }
  }

  /**
   * Method publishDrafts.
   *
   * Executes the publish drafts operation.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function publishDrafts(string $interventionId): void
  {
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->getRepository(EquipmentRecord::class)->findBy([
      'interventionId' => $interventionId,
      'recordStatus' => 'draft',
    ]);

    foreach ($records as $record) {
      // Materialize the side-effects the record skipped while it was a draft
      // scratchpad (drafts are exempt from them on the canonical surface). A draft
      // is "born" in stock, so the effective transition is in_stock -> its final
      // status: an asset published in service carries its first-commissioning date,
      // and one published under maintenance gets an open maintenance log — mirroring
      // the Commission / PutUnderMaintenance handlers.
      if (in_array($record->status, ['operational', 'under_maintenance'], true)) {
        $record->commissionedAt ??= new DateTimeImmutable();
      }

      $record->recordStatus = 'published';
      ++$record->revision;
      $record->updatedAt = new DateTimeImmutable();

      $organization = $record->organization;
      if ($organization instanceof OrganizationRecord && 'under_maintenance' === $record->status) {
        $this->maintenanceLogSynchronizer->syncForStatusTransition(
          $record->id,
          $organization->id,
          'in_stock',
          'under_maintenance',
        );
      }
    }

    $this->entityManager->flush();
  }

  /**
   * Method discardDrafts.
   *
   * Deletes the still-draft equipment records created for an intervention that
   * is abandoned or deleted. Published records are left untouched.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function discardDrafts(string $interventionId): void
  {
    $this->entityManager->createQueryBuilder()
      ->delete(EquipmentRecord::class, 'record')
      ->where('record.interventionId = :interventionId')
      ->andWhere('record.recordStatus = :draft')
      ->setParameter('interventionId', $interventionId)
      ->setParameter('draft', 'draft')
      ->getQuery()
      ->execute();
  }

  /**
   * Method assertLegalStatusTransition.
   *
   * Rejects an illegal published-equipment status transition, matching the
   * `Equipment` aggregate rules and the canonical mutation processor.
   *
   * @since 1.0.0
   *
   * @param string $from the current status
   * @param string $to the requested status
   */
  private function assertLegalStatusTransition(string $from, string $to): void
  {
    $allowed = self::ALLOWED_STATUS_TRANSITIONS[$from] ?? [];
    if (!in_array($to, $allowed, true)) {
      throw new InterventionConflictException(
        sprintf('Illegal equipment status transition from %s to %s.', $from, $to),
      );
    }
  }

  /**
   * Method id.
   *
   * Executes the id operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   *
   * @return string the id result
   */
  private function id(string $resource): string
  {
    return $this->resourceId($resource, 'equipment');
  }

  /**
   * Method assertPatchFields.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $patch
   */
  private function assertPatchFields(array $patch): void
  {
    $unknown = array_diff(array_keys($patch), self::PATCHABLE_FIELDS);
    if ([] !== $unknown) {
      throw new InterventionConflictException(sprintf('Unsupported equipment patch fields: %s.', implode(', ', $unknown)));
    }
  }

  /**
   * Method resourceId.
   *
   * Executes the resource id operation.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $collection the collection value
   *
   * @return string the resource id result
   */
  private function resourceId(string $resource, string $collection): string
  {
    if (1 !== preg_match(sprintf('#^/api/%s/([^/]+)$#', $collection), $resource, $matches)) {
      throw new InterventionConflictException(sprintf('Invalid %s resource IRI.', $collection));
    }

    return $matches[1];
  }

  /**
   * Method aggregateCounts.
   *
   * @since 1.0.0
   *
   * @param list<string> $interventionIds the intervention ids
   * @param bool $missingFacilityOnly whether only facility-less equipment is counted
   *
   * @return array<string, int> counts indexed by intervention id
   */
  private function aggregateCounts(array $interventionIds, bool $missingFacilityOnly): array
  {
    if ([] === $interventionIds) {
      return [];
    }
    $query = $this->entityManager->createQueryBuilder()
      ->select('record.interventionId AS interventionId', 'COUNT(record.id) AS total')
      ->from(EquipmentRecord::class, 'record')
      ->where('record.interventionId IN (:interventionIds)')
      ->setParameter('interventionIds', $interventionIds)
      ->groupBy('record.interventionId');
    if ($missingFacilityOnly) {
      $query->andWhere('record.facilityId IS NULL');
    }
    /** @var list<array{interventionId: string, total: string|int}> $rows */
    $rows = $query->getQuery()->getArrayResult();
    $counts = [];
    foreach ($rows as $row) {
      $counts[$row['interventionId']] = (int) $row['total'];
    }

    return $counts;
  }
}
