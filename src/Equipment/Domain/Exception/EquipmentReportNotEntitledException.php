<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentReportNotEntitledException.
 *
 * Thrown when an organization holds `organization.equipment.read` but its
 * current plan tier does not include the equipment sheet PDF export
 * (reserved to `pro`/`max`, the same gate as the Compliance safety
 * register). Distinct from `EquipmentAccessDeniedException` so the
 * Presentation layer can surface a dedicated "upgrade required" 403 message
 * — mirrors `Compliance\Domain\Exception\ComplianceExportNotEntitledException`.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentReportNotEntitledException extends RuntimeException
{
  // #region Methods
  /**
   * Method planTooLow.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function planTooLow(string $organizationId): self
  {
    return new self(sprintf('Organization "%s" plan does not include the equipment sheet PDF export. Upgrade to pro or max.', $organizationId));
  }
  // #endregion
}
