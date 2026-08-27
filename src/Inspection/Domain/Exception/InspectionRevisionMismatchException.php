<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception InspectionRevisionMismatchException.
 *
 * The optimistic-concurrency check, re-run INSIDE the handler's transaction.
 *
 * `RevisionGuard` already compares the `If-Match` revision in the processor,
 * but it does so against a scope read on the query bus — a separate
 * transaction from the one the mutation runs in. Re-comparing here closes
 * that window rather than trusting the earlier read. Mapped to 412, the same
 * status and the same wording `RevisionGuard` emits, so the two paths are
 * indistinguishable to a client.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionRevisionMismatchException extends RuntimeException
{
  // #region Methods
  /**
   * Method stale.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function stale(): self
  {
    return new self('The resource revision is stale.');
  }
  // #endregion
}
