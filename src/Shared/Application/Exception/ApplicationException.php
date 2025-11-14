<?php

declare(strict_types=1);

namespace Shared\Application\Exception;

use RuntimeException;

/**
 * Exception ApplicationException
 * @abstract
 *
 * Base class for exceptions occurring at the
 * application level. These represent orchestration failures,
 * transactional problems, etc.
 *
 * @category Exception
 * @package Shared\Application\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
abstract class ApplicationException extends RuntimeException
{
  //#region Methods
  /**
   * Method context
   *
   * Returns the context of
   * the exception.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The context of the exception.
   */
  public function context(): array
  {
    return [];
  }
  //#endregion
}
