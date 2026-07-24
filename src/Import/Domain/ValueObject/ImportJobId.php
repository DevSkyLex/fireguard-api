<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

use Shared\Domain\ValueObject\Uuid;

/**
 * ValueObject ImportJobId.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImportJobId extends Uuid
{
  // #region Methods
  /**
   * Method fromString.
   *
   * Creates an ImportJobId value object from a string.
   *
   * @since 1.0.0
   *
   * @param string $value the UUID value
   *
   * @return self the import job identifier
   */
  public static function fromString(string $value): self
  {
    return new self($value);
  }
  // #endregion
}
