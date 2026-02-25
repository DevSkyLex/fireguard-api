<?php

declare(strict_types=1);

namespace Shared\Application\Exception;

use Throwable;

/**
 * Exception MessengerRuntimeException.
 *
 * Thrown by command/query bus ports when message dispatching fails.
 * Wraps the underlying infrastructure exception for Application-layer handling.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerRuntimeException extends ApplicationException
{
  // #region Methods
  /**
   * Method wrap.
   *
   * @static
   *
   * Wrap a throwable exception.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception to wrap
   *
   * @return self the wrapped exception
   */
  public static function wrap(Throwable $exception): self
  {
    return new self(
      message: $exception->getMessage(),
      previous: $exception,
    );
  }
  // #endregion
}
