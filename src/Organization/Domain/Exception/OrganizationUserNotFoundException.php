<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationUserNotFoundException.
 *
 * Raised when a user identifier supplied to an organization operation matches
 * no user. Owned by Organization rather than reusing `User`'s own exception,
 * because a module may not import a sibling's `Domain` namespace.
 *
 * Mapped to 404: the referenced user genuinely does not exist. It answered 400
 * until 2026-08-26, which read as "your request is malformed" for a
 * well-formed request naming an absent resource.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationUserNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function create(): self
  {
    return new self('User not found.');
  }
  // #endregion
}
