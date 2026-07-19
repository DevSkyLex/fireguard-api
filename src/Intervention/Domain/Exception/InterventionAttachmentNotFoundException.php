<?php

declare(strict_types=1);

namespace Intervention\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception InterventionAttachmentNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAttachmentNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing intervention attachment identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the attachment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Intervention attachment with ID "%s" not found.', $id));
  }
  // #endregion
}
