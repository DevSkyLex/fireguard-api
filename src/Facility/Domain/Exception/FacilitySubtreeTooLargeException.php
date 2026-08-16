<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilitySubtreeTooLargeException.
 *
 * Raised when a facility subtree duplication would traverse and clone more
 * nodes than the defensive cap allows. Mapped to HTTP 422 at the API
 * boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilitySubtreeTooLargeException extends RuntimeException
{
  // #region Methods
  /**
   * Method exceedsLimit.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $facilityId the source facility identifier
   * @param int $nodeCount the number of nodes the subtree would traverse (source included)
   * @param int $limit the maximum number of nodes a duplication may traverse
   *
   * @return self the exception instance
   */
  public static function exceedsLimit(string $facilityId, int $nodeCount, int $limit): self
  {
    return new self(sprintf(
      'Facility "%s" subtree has %d nodes, which exceeds the %d-node duplication limit.',
      $facilityId,
      $nodeCount,
      $limit,
    ));
  }
  // #endregion
}
