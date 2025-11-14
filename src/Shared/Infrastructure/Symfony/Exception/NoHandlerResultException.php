<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;

use function sprintf;

/**
 * Exception NoHandlerResultException
 * @extends InfrastructureException
 * @final
 *
 * Exception thrown when a handler does
 * not return a result.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NoHandlerResultException extends InfrastructureException
{
  //#region Methods
  /**
   * Method forMessage
   * @method forMessage(object $message): self
   * @static
   *
   * Create a new NoHandlerResultException for a specific message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param object $message The message for which the exception is created.
   *
   * @return self The created exception.
   */
  public static function forMessage(object $message): self
  {
    return new self(
      message: sprintf('No handler result returned for message "%s".', $message::class)
    );
  }
  //#endregion
}
