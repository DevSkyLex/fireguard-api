<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilitySubtreeSourceArchivedException.
 *
 * Raised when a facility subtree duplication is requested for an archived
 * source facility. Duplicating an archived branch would otherwise un-archive
 * the copies implicitly, so the operation is refused outright rather than
 * silently reactivating the source's lineage. Mapped to HTTP 409 at the API
 * boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilitySubtreeSourceArchivedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the archived source facility identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $facilityId): self
  {
    return new self(sprintf(
      'Facility "%s" is archived and cannot be duplicated; restore it first.',
      $facilityId,
    ));
  }
  // #endregion
}
