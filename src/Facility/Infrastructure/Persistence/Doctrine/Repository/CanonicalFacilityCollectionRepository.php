<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Facility\{CanonicalFacilityCollectionItem, CanonicalFacilityPage};
use Facility\Application\Port\Outbound\CanonicalFacilityCollectionPort;

use function array_map;
use function in_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Repository CanonicalFacilityCollectionRepository.
 *
 * Uses scalar reads and one batched child predicate for canonical collections.
 *
 * @category Repository
 *
 * @phpstan-type FacilityRow array{id: string, organization_id: string, intervention_id: ?string, record_status: string, revision: int|string, parent_facility_id: ?string, has_children: bool|int|string, type: string, name: string, code: ?string, status: string, address: ?string, latitude: float|string|null, longitude: float|string|null, metadata: string, level_index: int|string|null, elevation_meters: float|string|null, height_meters: float|string|null, customer_id: ?string, created_at: string, updated_at: string}
 */
final readonly class CanonicalFacilityCollectionRepository implements CanonicalFacilityCollectionPort
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method page.
   */
  public function page(string $organizationId, ?string $interventionId, string $recordStatus, int $page, int $itemsPerPage): CanonicalFacilityPage
  {
    $connection = $this->entityManager->getConnection();
    $predicate = 'f.organization_id = :organization AND f.record_status = :status';
    $parameters = ['organization' => $organizationId, 'status' => $recordStatus];
    if (null !== $interventionId) {
      $predicate .= ' AND f.intervention_id = :intervention';
      $parameters['intervention'] = $interventionId;
    }
    /** @var int|string $matching */
    $matching = $connection->fetchOne('SELECT COUNT(*) FROM facilities f WHERE ' . $predicate, $parameters);
    $total = (int) $matching;
    /** @var list<FacilityRow> $rows */
    $rows = $connection->fetchAllAssociative(
      'SELECT f.*, EXISTS(SELECT 1 FROM facilities child WHERE child.parent_facility_id = f.id AND child.organization_id = f.organization_id) AS has_children FROM facilities f WHERE ' . $predicate . ' ORDER BY f.created_at ASC, f.id ASC LIMIT ' . $itemsPerPage . ' OFFSET ' . (($page - 1) * $itemsPerPage),
      $parameters,
    );

    return new CanonicalFacilityPage(array_map($this->mapRow(...), $rows), $total);
  }

  /**
   * Method mapRow
   *
   * Projects one scalar row without issuing additional database queries.
   *
   * @access private
   *
   * @param FacilityRow $row persisted collection row and grouped child predicate
   *
   * @return CanonicalFacilityCollectionItem typed canonical collection item
   */
  private function mapRow(array $row): CanonicalFacilityCollectionItem
  {
    /** @var array<string, mixed> $metadata */
    $metadata = json_decode((string) $row['metadata'], true, 512, JSON_THROW_ON_ERROR);

    return new CanonicalFacilityCollectionItem(
      id: (string) $row['id'],
      organizationId: (string) $row['organization_id'],
      interventionId: $row['intervention_id'],
      recordStatus: (string) $row['record_status'],
      revision: (int) $row['revision'],
      parentFacilityId: $row['parent_facility_id'],
      hasChildren: in_array($row['has_children'], [true, 1, 't', '1'], true),
      type: (string) $row['type'],
      name: (string) $row['name'],
      code: $row['code'],
      status: (string) $row['status'],
      address: $row['address'],
      latitude: $this->nullableFloat($row['latitude']),
      longitude: $this->nullableFloat($row['longitude']),
      metadata: $metadata,
      levelIndex: $this->nullableInt($row['level_index']),
      elevationMeters: $this->nullableFloat($row['elevation_meters']),
      heightMeters: $this->nullableFloat($row['height_meters']),
      customerId: $row['customer_id'],
      createdAt: new DateTimeImmutable((string) $row['created_at'])->format('c'),
      updatedAt: new DateTimeImmutable((string) $row['updated_at'])->format('c'),
    );
  }

  /**
   * Method nullableFloat
   *
   * Preserves null database values while normalizing numeric driver scalars.
   *
   * @access private
   *
   * @param float|string|null $value nullable database numeric value
   *
   * @return ?float normalized numeric value
   */
  private function nullableFloat(float|string|null $value): ?float
  {
    return null === $value ? null : (float) $value;
  }

  /**
   * Method nullableInt
   *
   * Preserves null database values while normalizing integer driver scalars.
   *
   * @access private
   *
   * @param int|string|null $value nullable database integer value
   *
   * @return ?int normalized integer value
   */
  private function nullableInt(int|string|null $value): ?int
  {
    return null === $value ? null : (int) $value;
  }
  // #endregion
}
