<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception InspectionAttachmentNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAttachmentNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing inspection attachment identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the attachment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection attachment with ID "%s" not found.', $id));
  }
  // #endregion
}
