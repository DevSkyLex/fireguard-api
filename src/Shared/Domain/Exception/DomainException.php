<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use RuntimeException;

/**
 * Exception DomainException
 * @abstract
 *
 * Base class for exceptions occurring at the
 * domain level. These represent business logic failures,
 * validation problems, etc.
 *
 * @category Exception
 * @package Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class DomainException extends RuntimeException
{
  //#region Methods
  /**
   * Method code
   *
   * Returns the code of
   * the exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The code of the exception.
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
  //#endregion
}
