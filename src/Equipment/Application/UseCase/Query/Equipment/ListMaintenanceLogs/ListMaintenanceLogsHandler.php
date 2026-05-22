<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

/**
 * UseCase ListMaintenanceLogsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceLogsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListMaintenanceLogsQuery $query): ListMaintenanceLogsResult
  {
    try {
      $equipmentId = EquipmentId::fromString($query->equipmentId);
      $organizationId = EquipmentOrganizationId::fromString($query->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $equipment = $this->equipmentRepository->findById($equipmentId);

    if (null === $equipment || (string) $equipment->organizationId() !== (string) $organizationId) {
      throw EquipmentNotFoundException::withId($query->equipmentId);
    }

    $total = $this->maintenanceLogRepository->countByEquipmentId($organizationId, $equipmentId);

    $logs = $this->maintenanceLogRepository->findByEquipmentId(
      $organizationId,
      $equipmentId,
      $query->pagination->limit,
      $query->pagination->offset,
    );

    return new ListMaintenanceLogsResult(
      logs: array_map(
        static fn ($log): array => [
          'id' => (string) $log->id(),
          'equipmentId' => (string) $log->equipmentId(),
          'organizationId' => (string) $log->organizationId(),
          'startedAt' => $log->startedAt()->format('c'),
          'completedAt' => $log->completedAt()?->format('c'),
        ],
        $logs,
      ),
      total: $total,
    );
  }
  // #endregion
}
