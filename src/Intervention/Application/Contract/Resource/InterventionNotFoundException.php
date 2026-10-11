<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

use RuntimeException;

use function sprintf;

/** Public resource failure.
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionNotFoundException extends RuntimeException
{
  public static function withId(string $id): self
  {
    return new self(sprintf('Intervention with ID "%s" not found.', $id));
  }
}
