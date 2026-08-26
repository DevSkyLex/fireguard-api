<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception CanonicalInspectionConflictException.
 *
 * A terminal inspection state refusing a canonical mutation. Mapped to 409 in
 * `config/packages/api_platform.yaml`.
 *
 * The two wordings below are the ones `CanonicalInspectionMutationProcessor`
 * emitted before the mutations moved into use cases, and they are separate on
 * purpose: a caller PATCHing a cancelled row and a caller DELETEing a closed
 * one are told different things.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalInspectionConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method terminalStateIsImmutable.
   *
   * Refuses a PATCH on a closed or cancelled inspection.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function terminalStateIsImmutable(): self
  {
    return new self('Closed or cancelled inspections are immutable.');
  }

  /**
   * Method closedCannotBeCancelled.
   *
   * Refuses the DELETE-as-cancel on a closed inspection. Closed is terminal,
   * mirroring `Inspection::cancel()`.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function closedCannotBeCancelled(): self
  {
    return new self('Closed inspections are immutable.');
  }
  // #endregion
}
