<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityMetadataFieldNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityMetadataFieldNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the metadata field identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Facility metadata field "%s" not found.', $id));
  }
  // #endregion
}
