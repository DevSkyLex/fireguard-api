<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Exception;

use RuntimeException;

/**
 * Exception InfrastructureException
 * @abstract
 *
 * Base class for exceptions occurring at the
 * infrastructure level. These represent problems
 * with the external world, such as database
 * connectivity, file system access, etc.
 *
 * @category Exception
 * @package Shared\Infrastructure\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class InfrastructureException extends RuntimeException
{
  //#region Methods
  /**
   * Method metadata
   *
   * Returns the metadata of
   * the exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The metadata of the exception.
   */
  public function metadata(): array
  {
    return [];
  }
  //#endregion
}
