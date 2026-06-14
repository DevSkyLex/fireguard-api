<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Intervention;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Port\Outbound\{InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionResourceOwnerPort};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionResourceNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Adapter InspectionInterventionResourceAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionInterventionResourceAdapter implements InterventionChangeApplierPort, InterventionDraftPublisherPort, InterventionResourceOwnerPort
{
  private const PATCHABLE_FIELDS = ['result', 'status', 'notes', 'signature'];

  private const RESULTS = ['pass', 'fail', 'partial'];

  private const STATUSES = ['draft', 'submitted', 'closed'];

  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionInterventionResourceAdapter class.
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
    return 1 === preg_match('#^/api/inspections/[^/]+$#', $resource);
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
    return InterventionResourceType::INSPECTION === $type;
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
    return $this->entityManager->find(InspectionRecord::class, $resourceId) instanceof InspectionRecord;
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
    $record = $this->entityManager->find(InspectionRecord::class, $resourceId);

    return $record instanceof InspectionRecord && $record->organization?->id === $organizationId;
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
    return null !== $this->entityManager->getRepository(InspectionRecord::class)->findOneBy(['clientId' => $clientId]);
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
    $record = $this->entityManager->find(InspectionRecord::class, $resourceId);
    if (!$record instanceof InspectionRecord) {
      throw InterventionResourceNotFoundException::withId(InterventionResourceType::INSPECTION, $resourceId);
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
    return $this->entityManager->getRepository(InspectionRecord::class)->count(['interventionId' => $interventionId]);
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
      ->from(InspectionRecord::class, 'record')
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
    $record = $this->entityManager->find(InspectionRecord::class, $this->id($resource));
    if (!$record instanceof InspectionRecord || $record->organization?->id !== $organizationId || 'published' !== $record->recordStatus) {
      throw new InterventionConflictException('Proposed inspection change target is invalid.');
    }
    if ('closed' === $record->status) {
      throw new InterventionConflictException('Closed inspections are immutable.');
    }

    if (array_key_exists('result', $patch)) {
      $result = $patch['result'];
      if (!is_string($result) || !in_array($result, self::RESULTS, true)) {
        throw new InterventionConflictException('Proposed inspection result is invalid.');
      }
      $record->result = $result;
    }
    if (array_key_exists('status', $patch)) {
      $status = $patch['status'];
      if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
        throw new InterventionConflictException('Proposed inspection status is invalid.');
      }
      $record->status = $status;
    }
    foreach (['notes', 'signature'] as $property) {
      if (array_key_exists($property, $patch)) {
        $value = $patch[$property];
        if (null !== $value && !is_string($value)) {
          throw new InterventionConflictException(sprintf('Inspection field "%s" must be a string or null.', $property));
        }
        $record->{$property} = $value;
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
      ->update(InspectionRecord::class, 'record')
      ->set('record.recordStatus', ':published')
      ->set('record.revision', 'record.revision + 1')
      ->where('record.interventionId = :interventionId')
      ->setParameter('published', 'published')
      ->setParameter('interventionId', $interventionId)
      ->getQuery()
      ->execute();
    $this->entityManager->createQueryBuilder()
      ->update(InspectionResponseRecord::class, 'record')
      ->set('record.recordStatus', ':published')
      ->set('record.revision', 'record.revision + 1')
      ->where('record.interventionId = :interventionId')
      ->setParameter('published', 'published')
      ->setParameter('interventionId', $interventionId)
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
    if (1 !== preg_match('#^/api/inspections/([^/]+)$#', $resource, $matches)) {
      throw new InterventionConflictException('Invalid inspection resource IRI.');
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
      throw new InterventionConflictException(sprintf('Unsupported inspection patch fields: %s.', implode(', ', $unknown)));
    }
  }
}
