<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Facility;

use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityPlanReferenceCleanupPort;

/**
 * Adapter EquipmentPlanReferenceCleanupAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentPlanReferenceCleanupAdapter implements FacilityPlanReferenceCleanupPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method clearForAttachment.
   *
   * @since 1.0.0
   */
  public function clearForAttachment(string $organizationId, string $attachmentId): void
  {
    $this->entityManager->getConnection()->executeStatement(
      "UPDATE equipment SET plan_position = NULL, revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE organization_id = :organizationId AND plan_position->>'attachmentId' = :attachmentId",
      ['organizationId' => $organizationId, 'attachmentId' => $attachmentId],
    );
  }
  // #endregion
}
