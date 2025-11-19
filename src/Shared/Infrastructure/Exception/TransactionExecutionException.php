<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;
use Throwable;

/**
 * Exception TransactionExecutionException
 * @final
 *
 * Exception thrown when executing a transactional
 * operation fails at the infrastructure level.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TransactionExecutionException extends InfrastructureException
{
  //#region Factory Methods
  /**
   * Method wrap
   * @static
   *
   * Wrap a throwable generated during a transactional operation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Throwable $previous The exception that occurred during the transaction.
   *
   * @return self The created exception instance.
   */
  public static function wrap(Throwable $previous): self
  {
    return new self(
      message: 'Failed to execute transactional operation.',
      previous: $previous
    );
  }
  //#endregion
}
