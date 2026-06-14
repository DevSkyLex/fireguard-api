<?php

declare(strict_types=1);

namespace Mission\Domain\Exception;

use Mission\Domain\ValueObject\MissionResourceType;
use RuntimeException;

use function sprintf;
use function ucfirst;

/**
 * Exception MissionResourceNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionResourceNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * Executes the with id operation.
   *
   * @since 1.0.0
   *
   * @param MissionResourceType $type the type value
   * @param string $id the id value
   *
   * @return self the with id result
   */
  public static function withId(MissionResourceType $type, string $id): self
  {
    return new self(sprintf('%s resource with ID "%s" not found.', ucfirst($type->value), $id));
  }
}
