<?php

declare(strict_types=1);

namespace Inspection\Application\Contract\Export;

/**
 * Domain NonConformityExportCandidate.
 *
 * A single non-conformity row as read from {@see \Inspection\Application\Port\Outbound\NonConformityRepositoryPort::listExportCandidates()},
 * carrying the raw `facilityId`/`equipmentId` identifiers resolved from its
 * owning inspection — no display name is resolved yet, mirroring
 * {@see InspectionExportCandidate}.
 * {@see \Inspection\Application\UseCase\Query\ExportNonConformities\ExportNonConformitiesHandler}
 * turns a batch of these into {@see NonConformityExportRow} once the
 * facility/equipment names have been resolved in bulk and the age has been
 * computed against the clock.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class NonConformityExportCandidate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the non-conformity identifier
   * @param string $inspectionId the owning inspection identifier
   * @param string $severity the non-conformity severity
   * @param string $status the non-conformity status
   * @param ?string $facilityId the site (facility) identifier of the owning inspection, if any
   * @param ?string $equipmentId the equipment identifier of the owning inspection, if any
   * @param string $createdAt the creation date, ISO 8601
   * @param ?string $resolvedAt the resolution date, ISO 8601, if any
   */
  public function __construct(
    public string $id,
    public string $inspectionId,
    public string $severity,
    public string $status,
    public ?string $facilityId,
    public ?string $equipmentId,
    public string $createdAt,
    public ?string $resolvedAt,
  ) {
  }
  // #endregion
}
