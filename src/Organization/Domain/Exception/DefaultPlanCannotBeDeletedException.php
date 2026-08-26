<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception DefaultPlanCannotBeDeletedException.
 *
 * Raised when a caller tries to delete the plan every organization falls back
 * to. Removing it would leave existing organizations pointing at nothing.
 *
 * Mapped to **409**, which is what `DeletePlanProcessor` already served — and
 * that processor was the one anomaly `PresentationExceptionStatusTest`
 * recorded: it caught the bare `InvalidArgumentException` and answered 409
 * where 86 other sites answered 400. With a dedicated class the anomaly stops
 * being an anomaly: 409 is now the declared status of a specific condition,
 * not a divergence in how a generic class is handled.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DefaultPlanCannotBeDeletedException extends RuntimeException
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
    return new self('The default plan cannot be deleted.');
  }
  // #endregion
}
