<?php

declare(strict_types=1);

namespace Compliance\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ComplianceExportNotEntitledException.
 *
 * Thrown when an organization holds `organization.compliance.export` but its
 * current plan tier does not include the regulatory PDF export (reserved to
 * `pro`/`max`). Distinct from `ComplianceAccessDeniedException` so the
 * Presentation layer can surface a dedicated "upgrade required" 403 message.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ComplianceExportNotEntitledException extends RuntimeException
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
    return new self(sprintf('Organization "%s" plan does not include the regulatory safety register export. Upgrade to pro or max.', $organizationId));
  }
  // #endregion
}
