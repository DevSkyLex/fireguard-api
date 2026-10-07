<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Intervention;

use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Intervention\Application\Contract\Result\InterventionInspectionResult;
use Intervention\Application\Port\Outbound\InterventionInspectionResultPort;

/**
 * Adapter InterventionInspectionResultAdapter.
 *
 * Reads an inspection fact within the same organization and intervention.
 *
 * @category Adapter
 */
final readonly class InterventionInspectionResultAdapter implements InterventionInspectionResultPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param EntityManagerInterface $entityManager the explicit main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method find.
   *
   * @param string $organizationId the publication organization
   * @param string $interventionId the owning intervention
   * @param string $inspectionId the recorded result identifier
   *
   * @return ?InterventionInspectionResult the scoped recorded fact
   */
  public function find(string $organizationId, string $interventionId, string $inspectionId): ?InterventionInspectionResult
  {
    $record = $this->entityManager->find(InspectionRecord::class, $inspectionId);
    if (!$record instanceof InspectionRecord || $record->organizationId() !== $organizationId || $record->interventionId !== $interventionId) {
      return null;
    }

    return new InterventionInspectionResult(
      id: $record->id,
      equipmentId: $record->equipmentId,
      result: $record->result,
      status: $record->status,
      performedAt: $record->performedAt,
      authorId: $record->inspectorUserId,
      notes: $record->notes,
    );
  }
  // #endregion
}
