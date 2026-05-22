<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum NonConformityStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum NonConformityStatus: string
{
  case OPEN = 'open';
  case IN_PROGRESS = 'in_progress';
  case DONE = 'done';
  case WAIVED = 'waived';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported non-conformity status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the non-conformity status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isResolved.
   *
   * @since 1.0.0
   */
  public function isResolved(): bool
  {
    return self::DONE === $this || self::WAIVED === $this;
  }

  public function label(): string
  {
    return match ($this) {
      self::OPEN => 'Open',
      self::IN_PROGRESS => 'In progress',
      self::DONE => 'Done',
      self::WAIVED => 'Waived',
    };
  }
  // #endregion
}
