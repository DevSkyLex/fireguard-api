<?php

declare(strict_types=1);

namespace Import\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ImportJobNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the import job id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Import job with ID "%s" not found.', $id));
  }

  /**
   * Method forOrganizationScope.
   *
   * Builds the exception for an organization the caller is not an active
   * member of, on a route whose scope the caller supplied directly (the
   * listing or the upload) rather than reaching through a job identifier.
   *
   * Deliberately the SAME class, and therefore the same 404, that an unknown
   * identifier produces: a distinct status here would tell an outsider which
   * organization identifiers are real.
   *
   * @static
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the for organization scope result
   */
  public static function forOrganizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }
  // #endregion
}
