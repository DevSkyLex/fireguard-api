<?php

declare(strict_types=1);

namespace Import\Domain\ValueObject;

use function array_column;

/**
 * Enum ImportStatus.
 *
 * The lifecycle status of an {@see \Import\Domain\Model\ImportJob\ImportJob}.
 * Transitions are guarded on the aggregate: `pending` -> `processing` ->
 * (`completed` | `failed`). A job is terminal once `completed` or `failed`.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ImportStatus: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case COMPLETED = 'completed';
  case FAILED = 'failed';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * Returns all supported import status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the import status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isTerminal.
   *
   * Reports whether the status is a terminal (final) state.
   *
   * @since 1.0.0
   *
   * @return bool true when the job can no longer transition
   */
  public function isTerminal(): bool
  {
    return self::COMPLETED === $this || self::FAILED === $this;
  }
  // #endregion
}
