<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Mission;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Mission\Application\Contract\Resource\MissionResourceAssignment;
use Mission\Application\Port\Outbound\{MissionChangeApplierPort, MissionDraftPublisherPort, MissionResourceOwnerPort};
use Mission\Domain\Exception\{MissionConflictException, MissionResourceNotFoundException};
use Mission\Domain\ValueObject\MissionResourceType;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Adapter FacilityMissionResourceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMissionResourceAdapter implements MissionChangeApplierPort, MissionDraftPublisherPort, MissionResourceOwnerPort
{
  private const PATCHABLE_FIELDS = ['type', 'name', 'code', 'address', 'metadata', 'status', 'parent'];

  private const STATUSES = ['active', 'archived'];

  private const TYPES = ['site', 'building', 'floor', 'zone', 'area'];

  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityMissionResourceAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
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
    return 1 === preg_match('#^/api/facilities/[^/]+$#', $resource);
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
    return MissionResourceType::FACILITY === $type;
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
    return $this->entityManager->find(FacilityRecord::class, $resourceId) instanceof FacilityRecord;
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
    $record = $this->entityManager->find(FacilityRecord::class, $resourceId);

    return $record instanceof FacilityRecord && $record->organization?->id === $organizationId;
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
    return null !== $this->entityManager->getRepository(FacilityRecord::class)->findOneBy(['clientId' => $clientId]);
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
    $record = $this->entityManager->find(FacilityRecord::class, $resourceId);
    if (!$record instanceof FacilityRecord) {
      throw MissionResourceNotFoundException::withId(MissionResourceType::FACILITY, $resourceId);
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
    return $this->entityManager->getRepository(FacilityRecord::class)->count(['missionId' => $missionId]);
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
    if ([] === $missionIds) {
      return [];
    }
    /** @var list<array{missionId: string, total: string|int}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('record.missionId AS missionId', 'COUNT(record.id) AS total')
      ->from(FacilityRecord::class, 'record')
      ->where('record.missionId IN (:missionIds)')
      ->setParameter('missionIds', $missionIds)
      ->groupBy('record.missionId')
      ->getQuery()
      ->getArrayResult();
    $counts = [];
    foreach ($rows as $row) {
      $counts[$row['missionId']] = (int) $row['total'];
    }

    return $counts;
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
    return [];
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
    $record = $this->entityManager->find(FacilityRecord::class, $this->id($resource));
    if (!$record instanceof FacilityRecord || $record->organization?->id !== $organizationId || 'published' !== $record->recordStatus) {
      throw new MissionConflictException('Proposed facility change target is invalid.');
    }

    if (array_key_exists('type', $patch)) {
      $type = $patch['type'];
      if (!is_string($type) || !in_array($type, self::TYPES, true)) {
        throw new MissionConflictException('Proposed facility type is invalid.');
      }
      $record->type = $type;
    }
    if (array_key_exists('name', $patch)) {
      $name = $patch['name'];
      if (!is_string($name) || '' === trim($name)) {
        throw new MissionConflictException('Facility name cannot be empty.');
      }
      $record->name = trim($name);
    }
    foreach (['code', 'address'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new MissionConflictException(sprintf('Facility field "%s" must be a string or null.', $property));
        }
        $record->{$property} = is_string($value) ? trim($value) : null;
      }
    }
    if (array_key_exists('metadata', $patch)) {
      if (!is_array($patch['metadata'])) {
        throw new MissionConflictException('Proposed facility metadata must be an object.');
      }
      /** @var array<string, mixed> $metadata */
      $metadata = $patch['metadata'];
      $record->metadata = $metadata;
    }
    if (array_key_exists('status', $patch)) {
      $status = $patch['status'];
      if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
        throw new MissionConflictException('Proposed facility status is invalid.');
      }
      $record->status = $status;
    }
    if (array_key_exists('parent', $patch)) {
      $parentIri = $patch['parent'];
      if (null === $parentIri) {
        $record->parentFacility = null;
      } elseif (is_string($parentIri)) {
        $parent = $this->entityManager->find(FacilityRecord::class, $this->id($parentIri));
        if (!$parent instanceof FacilityRecord || $parent->organization?->id !== $organizationId || $parent->id === $record->id) {
          throw new MissionConflictException('Proposed parent facility is invalid.');
        }
        $record->parentFacility = $parent;
      } else {
        throw new MissionConflictException('Proposed parent facility must be an IRI or null.');
      }
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
      ->update(FacilityRecord::class, 'record')
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
    if (1 !== preg_match('#^/api/facilities/([^/]+)$#', $resource, $matches)) {
      throw new MissionConflictException('Invalid facility resource IRI.');
    }

    return $matches[1];
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
      throw new MissionConflictException(sprintf('Unsupported facility patch fields: %s.', implode(', ', $unknown)));
    }
  }
}
