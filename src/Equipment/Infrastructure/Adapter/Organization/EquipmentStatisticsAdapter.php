<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Organization;

use Equipment\Application\Contract\Equipment\EquipmentListCriteria;
use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, EquipmentStatus, EquipmentType};
use Organization\Application\Port\Outbound\EquipmentStatisticsPort;

/**
 * Adapter EquipmentStatisticsAdapter.
 *
 * Implements the Organization module's equipment statistics port
 * using the Equipment module's repository.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentStatisticsAdapter implements EquipmentStatisticsPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Connects Organization's statistics port to Equipment's organization-scoped repository.
   *
   * @access public
   *
   * @param EquipmentRepositoryPort $equipmentRepository reads organization-scoped equipment aggregates
   *
   * @return void
   */
  public function __construct(
    private EquipmentRepositoryPort $equipmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method countEquipment.
   *
   * Counts equipment after translating organization id and optional filters to domain types.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $type optional equipment type filter
   * @param ?string $status optional equipment status filter
   *
   * @return int the matching equipment count
   */
  public function countEquipment(string $organizationId, ?string $type = null, ?string $status = null): int
  {
    return $this->equipmentRepository->countByOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
      new EquipmentListCriteria(type: $type, status: $status),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function countActiveEquipment(string $organizationId, ?string $type = null): int
  {
    $organization = EquipmentOrganizationId::fromString($organizationId);

    $total = $this->equipmentRepository->countByOrganizationId($organization, new EquipmentListCriteria(type: $type));
    $decommissioned = $this->equipmentRepository->countByOrganizationId(
      $organization,
      new EquipmentListCriteria(type: $type, status: EquipmentStatus::DECOMMISSIONED->value),
    );

    return $total - $decommissioned;
  }

  /**
   * Method countEquipmentOverview.
   *
   * Fills every equipment status bucket with zero when the repository has no rows for it.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $type optional equipment type filter
   * @param ?string $status optional equipment status filter
   *
   * @return array{total: int, in_stock: int, operational: int, under_maintenance: int, decommissioned: int} dashboard overview counts
   */
  public function countEquipmentOverview(string $organizationId, ?string $type = null, ?string $status = null): array
  {
    /** @var array<string, int> $overview */
    $overview = $this->equipmentRepository->countOverviewByOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
      type: $type,
      status: $status,
    );

    foreach (EquipmentStatus::cases() as $case) {
      if (!isset($overview[$case->value])) {
        $overview[$case->value] = 0;
      }
    }

    /** @var array{total: int, in_stock: int, operational: int, under_maintenance: int, decommissioned: int} $overview */
    return $overview;
  }

  /**
   * Method countEquipmentByStatus.
   *
   * Returns every known status bucket, including statuses with no equipment.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, int> equipment counts indexed by status value
   */
  public function countEquipmentByStatus(string $organizationId): array
  {
    $counts = $this->equipmentRepository->countByStatusForOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (EquipmentStatus::cases() as $status) {
      $normalizedCounts[$status->value] = $counts[$status->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countEquipmentByType.
   *
   * Returns the repository counts grouped by equipment type.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   *
   * @return array<string, int> equipment counts indexed by type value
   */
  public function countEquipmentByType(string $organizationId): array
  {
    $counts = $this->equipmentRepository->countByTypeForOrganizationId(
      EquipmentOrganizationId::fromString($organizationId),
    );
    $normalizedCounts = [];

    foreach (EquipmentType::cases() as $type) {
      $normalizedCounts[$type->value] = $counts[$type->value] ?? 0;
    }

    return $normalizedCounts;
  }

  /**
   * Method countEquipmentCreatedByDay.
   *
   * Delegates a date-bounded creation histogram with the optional filters intact.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $createdAtFrom inclusive period start
   * @param string $createdAtTo inclusive period end
   * @param ?string $timeZone timezone used for day buckets
   * @param ?string $type optional equipment type filter
   * @param ?string $status optional equipment status filter
   *
   * @return array<string, int> creation counts indexed by date
   */
  public function countEquipmentCreatedByDay(
    string $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
    ?string $status = null,
  ): array {
    return $this->equipmentRepository->countByCreatedDayForOrganizationId(
      organizationId: EquipmentOrganizationId::fromString($organizationId),
      createdAtFrom: $createdAtFrom,
      createdAtTo: $createdAtTo,
      timeZone: $timeZone,
      type: $type,
      status: $status,
    );
  }
  // #endregion
}
