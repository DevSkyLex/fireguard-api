<?php

declare(strict_types=1);

namespace Session\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Exception SessionNotFoundException
 * @final
 *
 * Thrown when a session cannot be found.
 *
 * @category Exception
 * @package Session\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionNotFoundException extends EntityNotFoundException
{
  //#region Methods
  /**
   * Method withId
   * @static
   *
   * Creates an exception for a missing session by ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The session ID.
   *
   * @return self The exception.
   */
  public static function withId(string $id): self
  {
    return new self(
      message: sprintf('Session with ID "%s" not found.', $id)
    );
  }
  //#endregion
}
