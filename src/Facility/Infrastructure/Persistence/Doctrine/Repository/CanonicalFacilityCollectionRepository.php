<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Contract\Facility\{CanonicalFacilityCollectionItem, CanonicalFacilityPage};
use Facility\Application\Port\Outbound\CanonicalFacilityCollectionPort;

use function array_map;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Repository CanonicalFacilityCollectionRepository.
 *
 * Uses scalar reads and one batched child predicate for canonical collections.
 *
 * @category Repository
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
    /** @var list<array{id: string, organization_id: string, intervention_id: ?string, record_status: string, revision: int|string, parent_facility_id: ?string, has_children: bool|int|string, type: string, name: string, code: ?string, status: string, address: ?string, latitude: float|string|null, longitude: float|string|null, metadata: string, level_index: int|string|null, elevation_meters: float|string|null, height_meters: float|string|null, customer_id: ?string, created_at: string, updated_at: string}> $rows */
    $rows = $connection->fetchAllAssociative(
      'SELECT f.*, EXISTS(SELECT 1 FROM facilities child WHERE child.parent_facility_id = f.id AND child.organization_id = f.organization_id) AS has_children FROM facilities f WHERE ' . $predicate . ' ORDER BY f.created_at ASC, f.id ASC LIMIT ' . $itemsPerPage . ' OFFSET ' . (($page - 1) * $itemsPerPage),
      $parameters,
    );

    return new CanonicalFacilityPage(array_map(static function (array $row): CanonicalFacilityCollectionItem {
      /** @var array<string, mixed> $metadata */
      $metadata = json_decode((string) $row['metadata'], true, 512, JSON_THROW_ON_ERROR);

      return new CanonicalFacilityCollectionItem(
        id: (string) $row['id'],
        organizationId: (string) $row['organization_id'],
        interventionId: null === $row['intervention_id'] ? null : (string) $row['intervention_id'],
        recordStatus: (string) $row['record_status'],
        revision: (int) $row['revision'],
        parentFacilityId: null === $row['parent_facility_id'] ? null : (string) $row['parent_facility_id'],
        hasChildren: true === $row['has_children'] || 1 === $row['has_children'] || 't' === $row['has_children'] || '1' === $row['has_children'],
        type: (string) $row['type'],
        name: (string) $row['name'],
        code: null === $row['code'] ? null : (string) $row['code'],
        status: (string) $row['status'],
        address: null === $row['address'] ? null : (string) $row['address'],
        latitude: null === $row['latitude'] ? null : (float) $row['latitude'],
        longitude: null === $row['longitude'] ? null : (float) $row['longitude'],
        metadata: $metadata,
        levelIndex: null === $row['level_index'] ? null : (int) $row['level_index'],
        elevationMeters: null === $row['elevation_meters'] ? null : (float) $row['elevation_meters'],
        heightMeters: null === $row['height_meters'] ? null : (float) $row['height_meters'],
        customerId: null === $row['customer_id'] ? null : (string) $row['customer_id'],
        createdAt: new DateTimeImmutable((string) $row['created_at'])->format('c'),
        updatedAt: new DateTimeImmutable((string) $row['updated_at'])->format('c'),
      );
    }, $rows), $total);
  }
  // #endregion
}
