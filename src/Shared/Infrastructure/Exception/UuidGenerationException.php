<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Exception;

use Shared\Infrastructure\Exception\InfrastructureException;

use function sprintf;
use Throwable;

/**
 * Exception UuidGenerationException
 * @final
 *
 * Exception thrown when generating a UUID fails.
 *
 * @category Exception
 * @package Shared\Infrastructure\Symfony\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UuidGenerationException extends InfrastructureException
{
  //#region Methods
  /**
   * Method dueToRandomFailure
   * @static
   *
   * Create an exception when the random source fails.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Throwable $previous The underlying exception triggered by the generator.
   *
   * @return self The created exception instance.
   */
  public static function dueToRandomFailure(Throwable $previous): self
  {
    return new self(
      message: sprintf('Unable to generate a UUID: %s', $previous->getMessage()),
      previous: $previous
    );
  }
  //#endregion
}
