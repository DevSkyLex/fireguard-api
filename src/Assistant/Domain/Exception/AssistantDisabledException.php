<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

/**
 * Exception AssistantDisabledException.
 *
 * The organization has switched the assistant off in its settings. Distinct
 * from a missing permission: the caller may well hold
 * `organization.assistant.use`, and granting more permissions will not help —
 * the organization has to turn the feature back on.
 *
 * Owned here rather than reusing `Organization`'s access-denied exception: a
 * module reaches a sibling through its `Application\Port` and
 * `Application\Contract` only, never its `Domain`
 * (`tests/Architecture/Unit/CrossModuleDomainBoundaryTest`).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantDisabledException extends RuntimeException
{
  // #region Methods
  /**
   * Method forOrganization.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function forOrganization(): self
  {
    return new self('The assistant is disabled for this organization.');
  }
  // #endregion
}
