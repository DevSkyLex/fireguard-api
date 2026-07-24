<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function implode;
use function sprintf;

/**
 * Exception OrganizationPlanUsageExceededException.
 *
 * Raised when a plan change would put the organization's CURRENT usage above
 * the candidate plan's caps and the caller has not acknowledged the overuse.
 * Mapped to HTTP 409 at the API boundary so the client can surface a
 * confirmation step before re-submitting with the acknowledgement flag.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationPlanUsageExceededException extends RuntimeException
{
  // #region Methods
  /**
   * Method withViolations.
   *
   * Creates an exception listing every resource whose usage exceeds the
   * candidate plan's cap.
   *
   * @since 1.0.0
   *
   * @param array<string, array{usage: int, limit: int}> $violations usage/limit per exceeded resource
   *
   * @return self the exception instance
   */
  public static function withViolations(array $violations): self
  {
    $parts = [];
    foreach ($violations as $resource => $numbers) {
      $parts[] = sprintf('%s %d/%d', $resource, $numbers['usage'], $numbers['limit']);
    }

    return new self(sprintf(
      'Current usage exceeds the selected plan limits (%s). Confirm the downgrade to apply it anyway; existing data is preserved but new creations stay blocked until usage fits the plan.',
      implode(', ', $parts),
    ));
  }
  // #endregion
}
