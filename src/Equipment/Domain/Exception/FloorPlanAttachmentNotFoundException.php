<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FloorPlanAttachmentNotFoundException.
 *
 * Raised by `EquipmentFloorPlanValidationPort` when the floor plan attachment
 * proposed for a plan position does not exist. Mapped to HTTP 404.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FloorPlanAttachmentNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the attachment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Floor plan attachment with ID "%s" not found.', $id));
  }
  // #endregion
}
