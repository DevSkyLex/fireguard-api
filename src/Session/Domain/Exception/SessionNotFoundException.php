<?php

declare(strict_types=1);

namespace Session\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception SessionNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionNotFoundException extends EntityNotFoundException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * Creates an exception for a missing session by ID.
   *
   * @since 1.0.0
   *
   * @param string $id the session ID
   *
   * @return self the exception
   */
  public static function withId(string $id): self
  {
    return new self(
      message: sprintf('Session with ID "%s" not found.', $id),
    );
  }
  // #endregion
}
