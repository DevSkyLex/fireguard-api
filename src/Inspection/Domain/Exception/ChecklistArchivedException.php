<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ChecklistArchivedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChecklistArchivedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for an already archived checklist.
   *
   * @since 1.0.0
   *
   * @param string $id the checklist identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Checklist with ID "%s" is already archived.', $id));
  }
  // #endregion
}
