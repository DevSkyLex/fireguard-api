<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;

use Throwable;


/**
 * Exception MessengerRuntimeException
 * @extends InfrastructureException
 * @final
 *
 * Exception thrown when a runtime
 * error occurs in the messenger.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessengerRuntimeException extends InfrastructureException
{
  //#region Methods
  /**
   * Method wrap
   * @method wrap(Throwable $exception): self
   * @static
   *
   * Wrap a throwable exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Throwable $exception The exception to wrap.
   *
   * @return self The wrapped exception.
   */
  public static function wrap(Throwable $exception): self
  {
    return new self(
      message: $exception->getMessage(),
      previous: $exception
    );
  }
  //#endregion
}
