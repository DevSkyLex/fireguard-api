<?php

declare(strict_types=1);

namespace Intervention\Domain\Exception;

use Intervention\Domain\ValueObject\InterventionResourceType;
use RuntimeException;

use function sprintf;
use function ucfirst;

/**
 * Exception InterventionResourceNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionResourceNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * Executes the with id operation.
   *
   * @since 1.0.0
   *
   * @param InterventionResourceType $type the type value
   * @param string $id the id value
   *
   * @return self the with id result
   */
  public static function withId(InterventionResourceType $type, string $id): self
  {
    return new self(sprintf('%s resource with ID "%s" not found.', ucfirst($type->value), $id));
  }
}
