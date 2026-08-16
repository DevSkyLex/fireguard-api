<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityMetadataFieldLimitExceededException.
 *
 * Raised when an organization has reached the cap on facility metadata
 * field definitions (50). Mapped to HTTP 422 at the API boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldLimitExceededException extends RuntimeException
{
  // #region Methods
  /**
   * Method withOrganizationId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param int $limit the maximum number of definitions allowed
   *
   * @return self the exception instance
   */
  public static function withOrganizationId(string $organizationId, int $limit): self
  {
    return new self(sprintf(
      'Organization "%s" has reached the limit of %d facility metadata field definitions.',
      $organizationId,
      $limit,
    ));
  }
  // #endregion
}
