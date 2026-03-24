<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use InvalidArgumentException;

/**
 * Port ChecklistValidationPort.
 *
 * Validates that a checklist exists, belongs to the expected organization,
 * and is active. This abstracts checklist ownership checks from the Inspection
 * creation flow without exposing the full checklist repository.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ChecklistValidationPort
{
  // #region Methods
  /**
   * Method assertChecklistIsUsable.
   *
   * Verifies that the given checklist exists, belongs to the specified
   * organization, and is in an active (non-archived) state.
   *
   * @since 1.0.0
   *
   * @param string $checklistId the checklist identifier
   * @param string $organizationId the expected organization identifier
   *
   * @throws InvalidArgumentException when the checklist is not found, belongs to another organization, or is archived
   */
  public function assertChecklistIsUsable(string $checklistId, string $organizationId): void;
  // #endregion
}
