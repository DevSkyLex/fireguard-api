<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Export;

/**
 * Domain InterventionExportRow.
 *
 * One intervention row ready for the CSV export, carrying the site and
 * responsible display names already resolved in bulk by
 * {@see \Intervention\Application\UseCase\Query\ExportInterventions\ExportInterventionsHandler}
 * through {@see \Intervention\Application\Port\Outbound\InterventionSiteNamingPort}
 * and {@see \Intervention\Application\Port\Outbound\InterventionMemberNamingPort} —
 * the Presentation-layer CSV writer only formats, it never resolves a name
 * itself. Deliberately a type distinct from {@see InterventionExportCandidate},
 * per the module's naming convention that contract types stay distinct from
 * use case Results and from each other's intermediate shapes.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionExportRow
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the intervention identifier
   * @param string $name the intervention name
   * @param string $type the intervention type
   * @param string $status the intervention status
   * @param string $priority the intervention priority
   * @param ?string $siteId the site (facility) identifier, if any
   * @param ?string $siteName the resolved facility display name, when the site is set and resolvable
   * @param ?string $responsibleId the responsible organization member identifier, if any
   * @param ?string $responsibleName the resolved responsible display name, when the member is set and resolvable
   * @param ?string $dueAt the due date, ISO 8601, if any
   * @param string $createdAt the creation date, ISO 8601
   * @param string $updatedAt the last update date, ISO 8601
   */
  public function __construct(
    public string $id,
    public string $name,
    public string $type,
    public string $status,
    public string $priority,
    public ?string $siteId,
    public ?string $siteName,
    public ?string $responsibleId,
    public ?string $responsibleName,
    public ?string $dueAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
