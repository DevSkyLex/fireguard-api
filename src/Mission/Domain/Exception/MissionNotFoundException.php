<?php

declare(strict_types=1);

namespace Mission\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MissionNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * Executes the with id operation.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Mission with ID "%s" not found.', $id));
  }
}
