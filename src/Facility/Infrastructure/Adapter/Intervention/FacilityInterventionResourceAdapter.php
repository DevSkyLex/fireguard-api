<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Port\Outbound\{InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function preg_match;

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
  private FacilityInterventionPatchApplier $patchApplier;

  /**
   * Constructor.
   *
   * Initializes a new instance of the FacilityInterventionResourceAdapter class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param FacilityArchivalGuardPort $archivalGuard the facility archival guard
   * @param FacilityRepositoryPort $facilityRepository the facility repository value
   * @param FacilityMetadataSchemaGuard $metadataSchemaGuard the organization's typed metadata schema guard
   * @param int $maxDepth the configured maximum facility hierarchy depth (root = 1)
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    FacilityArchivalGuardPort $archivalGuard,
    FacilityRepositoryPort $facilityRepository,
    FacilityMetadataSchemaGuard $metadataSchemaGuard,
    \Facility\Application\Port\Outbound\FacilityAttachmentRepositoryPort $attachments,
    \Facility\Application\Service\FacilityAttachmentAncestryGuard $planAncestry,
    #[Autowire('%facility.hierarchy.max_depth%')]
    int $maxDepth = 8,
  ) {
    $this->patchApplier = new FacilityInterventionPatchApplier(
      $entityManager,
      $archivalGuard,
      $facilityRepository,
      $metadataSchemaGuard,
      $attachments,
      $planAncestry,
      $maxDepth,
    );
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
    try {
      $this->patchApplier->apply($organizationId, $resource, $patch);
    } catch (FacilityPatchConflictException $exception) {
      throw new InterventionConflictException($exception->getMessage());
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
    // ORM writes keep revision fencing and the transactional audit listeners active.
    /** @var list<FacilityRecord> $records */
    $records = $this->entityManager->getRepository(FacilityRecord::class)->findBy([
      'interventionId' => $interventionId,
      'recordStatus' => 'draft',
    ]);
    foreach ($records as $record) {
      if (null !== $record->planGeometry) {
        try {
          $this->patchApplier->assertPlanUsable($record);
        } catch (FacilityPatchConflictException $exception) {
          throw new InterventionConflictException($exception->getMessage());
        }
      }
      $record->recordStatus = 'published';
      $record->updatedAt = new DateTimeImmutable();
    }
    $this->entityManager->flush();
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
}
