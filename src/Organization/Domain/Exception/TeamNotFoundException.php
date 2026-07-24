<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception TeamNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing team identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the team identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Team with ID "%s" not found.', $id));
  }
  // #endregion
}
