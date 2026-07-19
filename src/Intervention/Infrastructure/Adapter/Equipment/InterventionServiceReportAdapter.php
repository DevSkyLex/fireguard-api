<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Contract\Intervention\{InterventionServiceReport, ServicedEquipmentEntry};
use Equipment\Application\Port\Outbound\InterventionServiceReportPort;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionChangeRecord, InterventionRecord, InterventionWorkItemRecord};
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_key_exists;
use function preg_match;

/**
 * Adapter InterventionServiceReportAdapter.
 *
 * Implements the Equipment module's intervention service report port using
 * the Intervention module's own persistence records directly (mirrors the
 * Intervention module's other Doctrine adapters, e.g.
 * `Intervention\Infrastructure\Adapter\Organization\InterventionStatisticsAdapter`,
 * which query records through the main entity manager without an
 * intermediate repository indirection). Hosted in the provider module,
 * mirroring `Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter`.
 *
 * Scope: only applied changes to already-published equipment (`intervention.change_applier`),
 * never newly-created equipment drafts (`intervention.draft_publisher`) — creation
 * is not a service event.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionServiceReportAdapter implements InterventionServiceReportPort
{
  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function serviceReport(string $interventionId): ?InterventionServiceReport
  {
    $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$intervention instanceof InterventionRecord || !$intervention->organization instanceof OrganizationRecord) {
      return null;
    }

    /** @var list<InterventionChangeRecord> $changes */
    $changes = $this->entityManager->createQueryBuilder()
      ->select('change')
      ->from(InterventionChangeRecord::class, 'change')
      ->where('change.intervention = :intervention')
      ->andWhere('change.status = :status')
      ->andWhere('change.resource LIKE :pattern')
      ->setParameter('intervention', $intervention)
      ->setParameter('status', 'applied')
      ->setParameter('pattern', '/api/equipment/%')
      ->getQuery()
      ->getResult();

    $equipment = [];
    foreach ($changes as $change) {
      $entry = $this->toServicedEquipmentEntry($change);
      if (null !== $entry) {
        $equipment[] = $entry;
      }
    }

    return new InterventionServiceReport(
      number: $intervention->number,
      actorId: $intervention->responsibleId,
      equipment: $equipment,
    );
  }

  /**
   * Method toServicedEquipmentEntry.
   *
   * Extracts a serviced equipment entry from an applied change, skipping
   * anything that is not a single-segment `/api/equipment/{id}` change
   * (mirrors `Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter::supports()`).
   *
   * @since 1.0.0
   *
   * @param InterventionChangeRecord $change the applied change record
   *
   * @return ?ServicedEquipmentEntry the serviced equipment entry, when the change targets equipment
   */
  private function toServicedEquipmentEntry(InterventionChangeRecord $change): ?ServicedEquipmentEntry
  {
    if (1 !== preg_match('#^/api/equipment/([^/]+)$#', $change->resource, $matches)) {
      return null;
    }

    $workItem = $change->workItem;
    $action = $workItem instanceof InterventionWorkItemRecord ? $workItem->action : $this->deriveAction($change->patch);

    return new ServicedEquipmentEntry(
      equipmentId: $matches[1],
      action: $action,
      changeToken: $change->id,
      workItemId: $workItem?->id,
    );
  }

  /**
   * Method deriveAction.
   *
   * Derives a fallback action label from the patch when no work item is
   * linked to the applied change.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $patch the applied change patch
   *
   * @return string the derived action label
   */
  private function deriveAction(array $patch): string
  {
    return array_key_exists('status', $patch) ? 'status_change' : 'update';
  }
  // #endregion
}
