<?php

declare(strict_types=1);

namespace Maintenance\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MaintenanceNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MaintenanceNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the maintenance schedule id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Maintenance schedule with ID "%s" not found.', $id));
  }
}
