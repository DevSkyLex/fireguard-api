<?php

declare(strict_types=1);

namespace Mission\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception PublicationNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PublicationNotFoundException extends RuntimeException
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
    return new self(sprintf('Publication with ID "%s" not found.', $id));
  }
}
