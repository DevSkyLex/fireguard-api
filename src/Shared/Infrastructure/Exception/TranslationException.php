<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use function sprintf;
use Throwable;

/**
 * Exception TranslationException
 * @final
 *
 * Exception thrown when a translation operation fails.
 *
 * @category Exception
 * @package Shared\Infrastructure\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TranslationException extends InfrastructureException
{
  //#region Methods
  /**
   * Method translateFailed
   * @static
   *
   * Create an exception for a
   * failed translation lookup.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The translation identifier that failed.
   * @param ?Throwable $previous The underlying exception if any.
   *
   * @return self The created exception instance.
   */
  public static function translateFailed(string $id, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to translate message "%s".', $id),
      previous: $previous
    );
  }
  //#endregion
}
