<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/**
 * Port FacilityPlanReferenceCleanupPort.
 *
 * Clears equipment references owned by the Equipment module when a plan is deleted.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityPlanReferenceCleanupPort
{
  // #region Methods
  /**
   * Method clearForAttachment.
   *
   * Called within the same main transaction as deletion of the plan metadata.
   *
   * @since 1.0.0
   */
  public function clearForAttachment(string $organizationId, string $attachmentId): void;
  // #endregion
}
