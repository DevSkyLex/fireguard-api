<?php

declare(strict_types=1);

namespace Session\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception SessionAlreadyRevokedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionAlreadyRevokedException extends DomainException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * Creates an exception for an already revoked session.
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
      message: sprintf('Session with ID "%s" has already been revoked.', $id)
    );
  }
  // #endregion
}
