<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Draft\InterventionMaintenanceWork;

/**
 * Interface InterventionMaintenanceWorkPort
 *
 * Allows Maintenance to inspect and attach existing work without reading intervention records.
 *
 * @category Port
 */
interface InterventionMaintenanceWorkPort
{
  /**
   * Method status
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $interventionId work order identifier
   *
   * @return ?string lifecycle status, or null outside scope
   */
  public function status(string $organizationId, string $interventionId): ?string;

  /**
   * Method findOpenLegacyInspectionWork
   *
   * Reads all candidates so the caller can refuse ambiguous handovers.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param list<string> $equipmentIds equipment whose legacy work is being migrated
   *
   * @return array<string,list<InterventionMaintenanceWork>> candidates by equipment identifier
   */
  public function findOpenLegacyInspectionWork(string $organizationId, array $equipmentIds): array;

  /**
   * Method attachOccurrence
   *
   * Must run in the caller's main transaction; repeated identical attachments are harmless.
   *
   * @access public
   *
   * @param string $organizationId owning organization
   * @param string $workItemId legacy work item
   * @param string $operationId preventive plan identity
   * @param string $occurrenceId occurrence identity
   * @param string $operationKind control or maintenance
   *
   * @return void
   */
  public function attachOccurrence(string $organizationId, string $workItemId, string $operationId, string $occurrenceId, string $operationKind): void;
}
