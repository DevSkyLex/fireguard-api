<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Export;

/**
 * Domain EquipmentExportCandidate.
 *
 * A single equipment row as read from {@see \Equipment\Application\Port\Outbound\EquipmentRepositoryPort::listEquipmentExportCandidates()},
 * carrying only the raw `facilityId` identifier — no display name is resolved
 * yet. Mirrors `Intervention\Application\Contract\Export\InterventionExportCandidate`.
 * {@see \Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler}
 * turns a batch of these into {@see EquipmentExportRow} once the facility name
 * has been resolved in bulk.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentExportCandidate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the equipment identifier
   * @param string $type the equipment type
   * @param ?string $subType the equipment sub-type, if any
   * @param ?string $brand the equipment brand, if any
   * @param ?string $model the equipment model, if any
   * @param ?string $serialNumber the equipment serial number, if any
   * @param ?string $locationLabel the equipment location label, if any
   * @param string $status the equipment status
   * @param ?string $facilityId the assigned facility identifier, if any
   * @param ?string $installedAt the installation date, ISO 8601, if any
   * @param ?string $commissionedAt the commissioning date, ISO 8601, if any
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $type,
    public ?string $subType,
    public ?string $brand,
    public ?string $model,
    public ?string $serialNumber,
    public ?string $locationLabel,
    public string $status,
    public ?string $facilityId,
    public ?string $installedAt,
    public ?string $commissionedAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
