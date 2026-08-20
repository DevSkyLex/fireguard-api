<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Export;

/**
 * Domain InterventionExportCandidate.
 *
 * A single intervention row as read from {@see \Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort::listInterventionExportCandidates()},
 * carrying only the raw `siteId`/`responsibleId` identifiers — no display
 * name is resolved yet, mirroring {@see \Intervention\Application\Contract\Statistics\InterventionIdentifierCount}.
 * {@see \Intervention\Application\UseCase\Query\ExportInterventions\ExportInterventionsHandler}
 * turns a batch of these into {@see InterventionExportRow} once the site and
 * responsible names have been resolved in bulk.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionExportCandidate
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
   * @param ?string $responsibleId the responsible organization member identifier, if any
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
    public ?string $responsibleId,
    public ?string $dueAt,
    public string $createdAt,
    public string $updatedAt,
  ) {
  }
  // #endregion
}
