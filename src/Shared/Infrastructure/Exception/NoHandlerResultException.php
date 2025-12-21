<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use function sprintf;

/**
 * Exception NoHandlerResultException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NoHandlerResultException extends InfrastructureException
{
  // #region Methods
  /**
   * Method forMessage.
   *
   * @static
   *
   * Create a new NoHandlerResultException for a specific message.
   *
   * @since 1.0.0
   *
   * @param object $message the message for which the exception is created
   *
   * @return self the created exception
   */
  public static function forMessage(object $message): self
  {
    return new self(
      message: sprintf('No handler result returned for message "%s".', $message::class),
    );
  }
  // #endregion
}
