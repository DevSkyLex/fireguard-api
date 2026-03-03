<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception TagNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TagNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing tag identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the tag identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Tag with ID "%s" not found.', $id));
  }

  /**
   * Method withName.
   *
   * Creates an exception for a missing tag name.
   *
   * @since 1.0.0
   *
   * @param string $name the tag name
   *
   * @return self the exception instance
   */
  public static function withName(string $name): self
  {
    return new self(sprintf('Tag with name "%s" not found.', $name));
  }
  // #endregion
}
