<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType, ParameterType};
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Contract\Park\{ParkAnomaliesCounts, ParkAnomalyEntry};
use Inspection\Application\Port\Outbound\ParkAnomalyGatewayPort;
use Inspection\Infrastructure\Exception\InvalidStorageTimeZoneException;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Contract\Sorting\Sorting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Repository ParkAnomalyRepository.
 *
 * Reads only Inspection-owned tables. Every query shares published-inspection,
 * organization and unresolved-status predicates before pagination or aggregation.
 *
 * @category Repository
 */
final readonly class ParkAnomalyRepository implements ParkAnomalyGatewayPort
{
  // #region Constants
  /**
   * Constant BASE_SELECTION.
   */
  private const string BASE_SELECTION = ' FROM non_conformities nc INNER JOIN inspections i ON i.id = nc.inspection_id WHERE i.organization_id = :organizationId AND i.record_status = :published AND nc.status IN (:openStatuses)';

  /**
   * Constant EQUIPMENT_SCOPE.
   */
  private const string EQUIPMENT_SCOPE = ' AND i.equipment_id IN (:equipmentIds)';
  // #endregion

  // #region Properties
  /**
   * Property storageZone. Stored wall-clock timestamps are interpreted in the owning persistence zone.
   */
  private DateTimeZone $storageZone;
  // #endregion

  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager explicitly wired main manager
   * @param string $storageTimeZone the same storage zone used by NonConformityRepository
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')]
    string $storageTimeZone = 'UTC',
  ) {
    try {
      $this->storageZone = new DateTimeZone($storageTimeZone);
    } catch (Throwable $exception) {
      throw new InvalidStorageTimeZoneException('Invalid inspection storage time zone.', previous: $exception);
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method findCandidateEquipmentIds.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   *
   * @return list<string> published inspection equipment with unresolved findings
   */
  public function findCandidateEquipmentIds(string $organizationId): array
  {
    /** @var list<string> */
    return $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT DISTINCT i.equipment_id' . self::BASE_SELECTION . ' ORDER BY i.equipment_id ASC',
      $this->parameters($organizationId),
      ['openStatuses' => ArrayParameterType::STRING],
    );
  }

  /**
   * Method list.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds resolved equipment scope
   * @param Pagination $pagination SQL page bounds
   * @param Sorting $sorting allowed primary sort
   *
   * @return list<ParkAnomalyEntry> unresolved page
   */
  public function list(string $organizationId, array $equipmentIds, Pagination $pagination, Sorting $sorting): array
  {
    if ([] === $equipmentIds) {
      return [];
    }

    $sort = match ($sorting->field) {
      'id' => 'nc.id',
      'severity' => "CASE nc.severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END",
      'status' => 'nc.status',
      'dueAt' => 'nc.due_at',
      'updatedAt' => 'nc.updated_at',
      default => 'nc.created_at',
    };
    $direction = $sorting->direction->value;
    /** @var list<array{id: string, inspection_id: string, equipment_id: string, description: string, severity: string, status: string, due_at: ?string, resolved_at: ?string, notes: ?string, created_at: string, updated_at: string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative(
      'SELECT nc.id, nc.inspection_id, i.equipment_id, nc.description, nc.severity, nc.status, nc.due_at, nc.resolved_at, nc.notes, nc.created_at, nc.updated_at'
        . self::BASE_SELECTION . self::EQUIPMENT_SCOPE . ' ORDER BY ' . $sort . ' ' . $direction . ', nc.id DESC LIMIT :limit OFFSET :offset',
      $this->parameters($organizationId) + ['equipmentIds' => $equipmentIds, 'limit' => $pagination->limit, 'offset' => $pagination->offset],
      ['openStatuses' => ArrayParameterType::STRING, 'equipmentIds' => ArrayParameterType::STRING, 'limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
    );
    $entries = [];
    foreach ($rows as $row) {
      $entries[] = new ParkAnomalyEntry(
        id: $row['id'],
        inspectionId: $row['inspection_id'],
        equipmentId: $row['equipment_id'],
        description: $row['description'],
        severity: $row['severity'],
        status: $row['status'],
        dueAt: null === $row['due_at'] ? null : new DateTimeImmutable($row['due_at'], $this->storageZone),
        resolvedAt: null === $row['resolved_at'] ? null : new DateTimeImmutable($row['resolved_at'], $this->storageZone),
        notes: $row['notes'],
        createdAt: new DateTimeImmutable($row['created_at'], $this->storageZone),
        updatedAt: new DateTimeImmutable($row['updated_at'], $this->storageZone),
      );
    }

    return $entries;
  }

  /**
   * Method count.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds resolved equipment scope
   *
   * @return int total matching findings before pagination
   */
  public function count(string $organizationId, array $equipmentIds): int
  {
    if ([] === $equipmentIds) {
      return 0;
    }

    /** @var int|string $count */
    $count = $this->entityManager->getConnection()->fetchOne(
      'SELECT COUNT(*)' . self::BASE_SELECTION . self::EQUIPMENT_SCOPE,
      $this->parameters($organizationId) + ['equipmentIds' => $equipmentIds],
      ['openStatuses' => ArrayParameterType::STRING, 'equipmentIds' => ArrayParameterType::STRING],
    );

    return (int) $count;
  }

  /**
   * Method summary.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds resolved equipment scope
   *
   * @return ParkAnomaliesCounts zero-inclusive scoped totals
   */
  public function summary(string $organizationId, array $equipmentIds): ParkAnomaliesCounts
  {
    if ([] === $equipmentIds) {
      return new ParkAnomaliesCounts(0);
    }

    /** @var array{total: int|string, low: int|string, medium: int|string, high: int|string, critical: int|string} $row */
    $row = $this->entityManager->getConnection()->fetchAssociative(
      "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE nc.severity = 'low') AS low, COUNT(*) FILTER (WHERE nc.severity = 'medium') AS medium, COUNT(*) FILTER (WHERE nc.severity = 'high') AS high, COUNT(*) FILTER (WHERE nc.severity = 'critical') AS critical"
        . self::BASE_SELECTION . self::EQUIPMENT_SCOPE,
      $this->parameters($organizationId) + ['equipmentIds' => $equipmentIds],
      ['openStatuses' => ArrayParameterType::STRING, 'equipmentIds' => ArrayParameterType::STRING],
    );

    return new ParkAnomaliesCounts((int) $row['total'], [
      'low' => (int) $row['low'],
      'medium' => (int) $row['medium'],
      'high' => (int) $row['high'],
      'critical' => (int) $row['critical'],
    ]);
  }

  /**
   * Method parameters.
   *
   * @access private
   *
   * @param string $organizationId owning organization
   *
   * @return array{organizationId: string, published: string, openStatuses: list<string>} shared predicates
   */
  private function parameters(string $organizationId): array
  {
    return ['organizationId' => $organizationId, 'published' => 'published', 'openStatuses' => ['open', 'in_progress']];
  }
  // #endregion
}
