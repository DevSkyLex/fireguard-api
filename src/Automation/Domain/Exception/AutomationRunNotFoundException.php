<?php

declare(strict_types=1);

namespace Automation\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception AutomationRunNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AutomationRunNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * @since 1.0.0
   *
   * @param string $id the id value
   *
   * @return self the with id result
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Automation run with ID "%s" not found.', $id));
  }
}
