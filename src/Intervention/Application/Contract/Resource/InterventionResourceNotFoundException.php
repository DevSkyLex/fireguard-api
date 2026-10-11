<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

use RuntimeException;

use function sprintf;
use function ucfirst;

/** Public resource failure.
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionResourceNotFoundException extends RuntimeException
{
  public static function withId(InterventionResourceType $type, string $id): self
  {
    return new self(sprintf('%s resource with ID "%s" not found.', ucfirst($type->value), $id));
  }
}
