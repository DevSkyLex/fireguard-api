<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use RuntimeException;

use function end;
use function explode;
use function preg_replace;
use function strtoupper;

/**
 * Exception DomainException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class DomainException extends RuntimeException
{
  // #region Methods
  /**
   * Method code.
   *
   * Returns the code of
   * the exception.
   *
   * @since 1.0.0
   *
   * @return string the code of the exception
   */
  public function code(): string
  {
    $parts = explode('\\', static::class);
    // end() cannot return false here since static::class always produces a non-empty array
    $className = end($parts) ?: '';

    $result = preg_replace(
      pattern: '/(?<!^)[A-Z]/',
      replacement: '_$0',
      subject: $className
    );

    return strtoupper($result ?? '');
  }
  // #endregion
}
