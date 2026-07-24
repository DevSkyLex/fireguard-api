<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception TeamNameAlreadyExistsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamNameAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withName.
   *
   * Creates an exception for a duplicate team name within an organization.
   *
   * @since 1.0.0
   *
   * @param string $name the duplicate team name
   *
   * @return self the exception instance
   */
  public static function withName(string $name): self
  {
    return new self(sprintf('Team "%s" already exists for this organization.', $name));
  }
  // #endregion
}
