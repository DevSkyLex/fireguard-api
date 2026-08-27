<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository, QueryBuilder};
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\{InspectionOrganizationId, InspectionResponseId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionResponseMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;

/**
 * Repository InspectionResponseRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionResponseRepository implements InspectionResponseRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<InspectionResponseRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the `main` entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(InspectionResponseRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(InspectionResponse $response): void
  {
    $record = $this->repository->find((string) $response->id());
    $isNew = !$record instanceof InspectionResponseRecord;
    $record = $isNew ? new InspectionResponseRecord() : $record;

    InspectionResponseMapper::applyTo($response, $record);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $response->organizationId());
    $record->organization = $organization;

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(InspectionResponseId $id): ?InspectionResponse
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionResponseRecord) {
      return null;
    }

    return InspectionResponseMapper::toDomain($record);
  }

  /**
   * Method existsByClientId.
   *
   * @since 1.0.0
   */
  public function existsByClientId(string $clientId): bool
  {
    return $this->repository->count(['clientId' => $clientId]) > 0;
  }

  /**
   * Method findByFilters.
   *
   * @since 1.0.0
   */
  public function findByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $inspectionId,
    string $recordStatus,
    int $limit,
    int $offset,
  ): array {
    /** @var list<InspectionResponseRecord> $records */
    $records = $this
      ->filtered($organizationId, $interventionId, $inspectionId, $recordStatus)
      ->select('r')
      ->orderBy('r.createdAt', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (InspectionResponseRecord $record): InspectionResponse => InspectionResponseMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByFilters.
   *
   * @since 1.0.0
   */
  public function countByFilters(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $inspectionId,
    string $recordStatus,
  ): int {
    return (int) $this
      ->filtered($organizationId, $interventionId, $inspectionId, $recordStatus)
      ->select('COUNT(r.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(InspectionResponseId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionResponseRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method filtered.
   *
   * The one place the collection's WHERE clause is written, so the count and
   * the page can never drift apart — a paginator whose total is computed
   * over different rows than its page is the classic way to ship a listing
   * that says 12 and shows 7.
   *
   * @since 1.0.0
   *
   * @param InspectionOrganizationId $organizationId the owning organization
   * @param ?string $interventionId narrow to one intervention, or null
   * @param ?string $inspectionId narrow to one inspection, or null
   * @param string $recordStatus the representation lifecycle status
   *
   * @return QueryBuilder the shared filter clause
   */
  private function filtered(
    InspectionOrganizationId $organizationId,
    ?string $interventionId,
    ?string $inspectionId,
    string $recordStatus,
  ): QueryBuilder {
    $query = $this->entityManager->createQueryBuilder()
      ->from(InspectionResponseRecord::class, 'r')
      ->where('r.organization = :organization')
      ->setParameter('organization', (string) $organizationId)
      ->andWhere('r.recordStatus = :status')
      ->setParameter('status', $recordStatus);

    if (null !== $interventionId) {
      $query->andWhere('r.interventionId = :intervention')->setParameter('intervention', $interventionId);
    }

    if (null !== $inspectionId) {
      $query->andWhere('r.inspectionId = :inspection')->setParameter('inspection', $inspectionId);
    }

    return $query;
  }
  // #endregion
}
