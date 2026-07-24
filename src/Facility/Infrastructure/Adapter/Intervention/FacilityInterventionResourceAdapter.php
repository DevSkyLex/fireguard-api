<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Port\Outbound\{InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;

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
 * Adapter FacilityInterventionResourceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityInterventionResourceAdapter implements InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionResourceOwnerPort
{
  private const PATCHABLE_FIELDS = ['type', 'name', 'code', 'address', 'metadata', 'status', 'parent'];

  private const STATUSES = ['active', 'archived'];

  private const TYPES = ['site', 'building', 'floor', 'zone', 'area'];

  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityInterventionResourceAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param FacilityArchivalGuardPort $archivalGuard the facility archival guard
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityArchivalGuardPort $archivalGuard,
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
    return 1 === preg_match('#^/api/facilities/[^/]+$#', $resource);
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
    return InterventionResourceType::FACILITY === $type;
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
   * @param ?string $interventionId the intervention id value
   * @param ?string $clientId the client id value
   *
   * @return InterventionResourceAssignment the assign result
   */
  public function assign(string $resourceId, ?string $interventionId, ?string $clientId): InterventionResourceAssignment
  {
    $record = $this->entityManager->find(FacilityRecord::class, $resourceId);
    if (!$record instanceof FacilityRecord) {
      throw InterventionResourceNotFoundException::withId(InterventionResourceType::FACILITY, $resourceId);
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
    return $this->entityManager->getRepository(FacilityRecord::class)->count(['interventionId' => $interventionId]);
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
    if ([] === $interventionIds) {
      return [];
    }
    /** @var list<array{interventionId: string, total: string|int}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('record.interventionId AS interventionId', 'COUNT(record.id) AS total')
      ->from(FacilityRecord::class, 'record')
      ->where('record.interventionId IN (:interventionIds)')
      ->setParameter('interventionIds', $interventionIds)
      ->groupBy('record.interventionId')
      ->getQuery()
      ->getArrayResult();
    $counts = [];
    foreach ($rows as $row) {
      $counts[$row['interventionId']] = (int) $row['total'];
    }

    return $counts;
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
      throw new InterventionConflictException('Proposed facility change target is invalid.');
    }
    $previousStatus = $record->status;

    if (array_key_exists('type', $patch)) {
      $type = $patch['type'];
      if (!is_string($type) || !in_array($type, self::TYPES, true)) {
        throw new InterventionConflictException('Proposed facility type is invalid.');
      }
      $record->type = $type;
    }
    if (array_key_exists('name', $patch)) {
      $name = $patch['name'];
      if (!is_string($name) || '' === trim($name)) {
        throw new InterventionConflictException('Facility name cannot be empty.');
      }
      $record->name = trim($name);
    }
    foreach (['code', 'address'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new InterventionConflictException(sprintf('Facility field "%s" must be a string or null.', $property));
        }
        $record->{$property} = is_string($value) ? trim($value) : null;
      }
    }
    if (array_key_exists('metadata', $patch)) {
      if (!is_array($patch['metadata'])) {
        throw new InterventionConflictException('Proposed facility metadata must be an object.');
      }
      /** @var array<string, mixed> $metadata */
      $metadata = $patch['metadata'];
      $record->metadata = $metadata;
    }
    if (array_key_exists('status', $patch)) {
      $status = $patch['status'];
      if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
        throw new InterventionConflictException('Proposed facility status is invalid.');
      }
      $record->status = $status;
    }
    if (array_key_exists('parent', $patch)) {
      $parentIri = $patch['parent'];
      if (null === $parentIri) {
        $record->parentFacility = null;
      } elseif (is_string($parentIri)) {
        $parent = $this->entityManager->find(FacilityRecord::class, $this->id($parentIri));
        if (!$parent instanceof FacilityRecord || $parent->organization?->id !== $organizationId) {
          throw new InterventionConflictException('Proposed parent facility is invalid.');
        }
        $this->assertNoParentCycle($record, $parent);
        if ('archived' === $parent->status) {
          throw new InterventionConflictException('Proposed parent facility is archived.');
        }
        $record->parentFacility = $parent;
      } else {
        throw new InterventionConflictException('Proposed parent facility must be an IRI or null.');
      }
    }

    // Restoring (archived -> active) is refused while the parent is archived,
    // mirroring the RestoreFacility use case and the canonical mutation processor.
    if (
      'archived' === $previousStatus
      && 'active' === $record->status
      && $record->parentFacility instanceof FacilityRecord
      && 'archived' === $record->parentFacility->status
    ) {
      throw new InterventionConflictException('Cannot restore a facility while its parent is archived.');
    }

    // Archiving must not orphan a live dependent, mirroring the canonical surface.
    if ('archived' !== $previousStatus && 'archived' === $record->status) {
      try {
        $this->archivalGuard->assertNoActiveDependents($organizationId, $record->id);
      } catch (FacilityHasActiveDependentsException $exception) {
        throw new InterventionConflictException($exception->getMessage());
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
   * @param string $interventionId the intervention id value
   */
  public function publishDrafts(string $interventionId): void
  {
    $this->entityManager->createQueryBuilder()
      ->update(FacilityRecord::class, 'record')
      ->set('record.recordStatus', ':published')
      ->set('record.revision', 'record.revision + 1')
      ->where('record.interventionId = :interventionId')
      ->setParameter('published', 'published')
      ->setParameter('interventionId', $interventionId)
      ->getQuery()
      ->execute();
  }

  /**
   * Method discardDrafts.
   *
   * Deletes the still-draft facility records created for an intervention that is
   * abandoned or deleted. Published records are left untouched.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   */
  public function discardDrafts(string $interventionId): void
  {
    $this->entityManager->createQueryBuilder()
      ->delete(FacilityRecord::class, 'record')
      ->where('record.interventionId = :interventionId')
      ->andWhere('record.recordStatus = :draft')
      ->setParameter('interventionId', $interventionId)
      ->setParameter('draft', 'draft')
      ->getQuery()
      ->execute();
  }

  /**
   * Method assertNoParentCycle.
   *
   * Rejects a parent assignment that would create a hierarchy cycle by walking
   * the proposed parent's ancestry back to the record being reparented.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the facility being reparented
   * @param FacilityRecord $parent the proposed parent
   */
  private function assertNoParentCycle(FacilityRecord $record, FacilityRecord $parent): void
  {
    $ancestor = $parent;
    while ($ancestor instanceof FacilityRecord) {
      if ($ancestor->id === $record->id) {
        throw new InterventionConflictException('Proposed parent facility would create a hierarchy cycle.');
      }
      $ancestor = $ancestor->parentFacility;
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
    if (1 !== preg_match('#^/api/facilities/([^/]+)$#', $resource, $matches)) {
      throw new InterventionConflictException('Invalid facility resource IRI.');
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
      throw new InterventionConflictException(sprintf('Unsupported facility patch fields: %s.', implode(', ', $unknown)));
    }
  }
}
