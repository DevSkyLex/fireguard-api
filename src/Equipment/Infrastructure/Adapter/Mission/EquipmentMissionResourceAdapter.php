<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Mission;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilityValidationPort;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Mission\Application\Contract\Resource\{MissionEquipmentDraft, MissionResourceAssignment};
use Mission\Application\Port\Outbound\{MissionChangeApplierPort, MissionDraftPublisherPort, MissionEquipmentDraftProviderPort, MissionResourceOwnerPort};
use Mission\Domain\Exception\{MissionConflictException, MissionResourceNotFoundException};
use Mission\Domain\ValueObject\MissionResourceType;
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
 * Adapter EquipmentMissionResourceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMissionResourceAdapter implements MissionChangeApplierPort, MissionDraftPublisherPort, MissionEquipmentDraftProviderPort, MissionResourceOwnerPort
{
  private const PATCHABLE_FIELDS = ['type', 'subType', 'brand', 'model', 'serialNumber', 'locationLabel', 'status', 'facility'];

  private const STATUSES = ['in_stock', 'operational', 'decommissioned', 'under_maintenance'];

  /**
   * Constructor.
   *
   * Initializes a new instance of the EquipmentMissionResourceAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param FacilityValidationPort $facilityValidation the facility validation value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityValidationPort $facilityValidation,
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
   * @param MissionResourceType $type the type value
   *
   * @return bool the supports resource type result
   */
  public function supportsResourceType(MissionResourceType $type): bool
  {
    return MissionResourceType::EQUIPMENT === $type;
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
   * @param ?string $missionId the mission id value
   * @param ?string $clientId the client id value
   *
   * @return MissionResourceAssignment the assign result
   */
  public function assign(string $resourceId, ?string $missionId, ?string $clientId): MissionResourceAssignment
  {
    $record = $this->entityManager->find(EquipmentRecord::class, $resourceId);
    if (!$record instanceof EquipmentRecord) {
      throw MissionResourceNotFoundException::withId(MissionResourceType::EQUIPMENT, $resourceId);
    }
    $record->clientId = $clientId;
    $record->missionId = $missionId;
    $record->recordStatus = null === $missionId ? 'published' : 'draft';
    $record->revision = 1;
    $this->entityManager->flush();

    return new MissionResourceAssignment($missionId, $record->recordStatus, $record->revision);
  }

  /**
   * Method countForMission.
   *
   * Executes the count for mission operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   *
   * @return int the count for mission result
   */
  public function countForMission(string $missionId): int
  {
    return $this->entityManager->getRepository(EquipmentRecord::class)->count(['missionId' => $missionId]);
  }

  /**
   * Method countsForMissions.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, int> counts indexed by mission id
   */
  public function countsForMissions(array $missionIds): array
  {
    return $this->aggregateCounts($missionIds, false);
  }

  /**
   * Method blockerCountsForMissions.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   *
   * @return array<string, int> blocker counts indexed by mission id
   */
  public function blockerCountsForMissions(array $missionIds): array
  {
    return $this->aggregateCounts($missionIds, true);
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
    /** @var list<EquipmentRecord> $records */
    $records = $this->entityManager->getRepository(EquipmentRecord::class)->findBy(['missionId' => $missionId]);

    return array_map(
      static fn (EquipmentRecord $record): MissionEquipmentDraft => new MissionEquipmentDraft(
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
      throw new MissionConflictException('Proposed equipment change target is invalid.');
    }

    if (array_key_exists('type', $patch)) {
      $type = $patch['type'];
      if (!is_string($type) || '' === $type) {
        throw new MissionConflictException('Equipment type cannot be empty.');
      }
      $record->type = $type;
    }

    foreach (['subType', 'brand', 'model', 'serialNumber', 'locationLabel'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new MissionConflictException(sprintf('Equipment field "%s" must be a string or null.', $property));
        }
        $record->{$property} = $value;
      }
    }

    if (array_key_exists('status', $patch)) {
      $status = $patch['status'];
      if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
        throw new MissionConflictException('Proposed equipment status is invalid.');
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
          throw new MissionConflictException('Proposed equipment facility is invalid.');
        }
        $record->facilityId = $facilityId;
      } else {
        throw new MissionConflictException('Proposed equipment facility must be an IRI or null.');
      }
    }

    if ('operational' === $record->status && null === $record->facilityId) {
      throw new MissionConflictException('Operational equipment must be assigned to a facility.');
    }

    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method publishDrafts.
   *
   * Executes the publish drafts operation.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   */
  public function publishDrafts(string $missionId): void
  {
    $this->entityManager->createQueryBuilder()
      ->update(EquipmentRecord::class, 'record')
      ->set('record.recordStatus', ':published')
      ->set('record.revision', 'record.revision + 1')
      ->where('record.missionId = :missionId')
      ->setParameter('published', 'published')
      ->setParameter('missionId', $missionId)
      ->getQuery()
      ->execute();
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
      throw new MissionConflictException(sprintf('Unsupported equipment patch fields: %s.', implode(', ', $unknown)));
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
      throw new MissionConflictException(sprintf('Invalid %s resource IRI.', $collection));
    }

    return $matches[1];
  }

  /**
   * Method aggregateCounts.
   *
   * @since 1.0.0
   *
   * @param list<string> $missionIds the mission ids
   * @param bool $missingFacilityOnly whether only facility-less equipment is counted
   *
   * @return array<string, int> counts indexed by mission id
   */
  private function aggregateCounts(array $missionIds, bool $missingFacilityOnly): array
  {
    if ([] === $missionIds) {
      return [];
    }
    $query = $this->entityManager->createQueryBuilder()
      ->select('record.missionId AS missionId', 'COUNT(record.id) AS total')
      ->from(EquipmentRecord::class, 'record')
      ->where('record.missionId IN (:missionIds)')
      ->setParameter('missionIds', $missionIds)
      ->groupBy('record.missionId');
    if ($missingFacilityOnly) {
      $query->andWhere('record.facilityId IS NULL');
    }
    /** @var list<array{missionId: string, total: string|int}> $rows */
    $rows = $query->getQuery()->getArrayResult();
    $counts = [];
    foreach ($rows as $row) {
      $counts[$row['missionId']] = (int) $row['total'];
    }

    return $counts;
  }
}
