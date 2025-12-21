<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use Throwable;

use function sprintf;

/**
 * Exception TranslationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TranslationException extends InfrastructureException
{
  // #region Methods
  /**
   * Method translateFailed.
   *
   * @static
   *
   * Create an exception for a
   * failed translation lookup.
   *
   * @since 1.0.0
   *
   * @param string $id the translation identifier that failed
   * @param ?Throwable $previous the underlying exception if any
   *
   * @return self the created exception instance
   */
  public static function translateFailed(string $id, ?Throwable $previous = null): self
  {
    return new self(
      message: sprintf('Failed to translate message "%s".', $id),
      previous: $previous,
    );
  }
  // #endregion
}
