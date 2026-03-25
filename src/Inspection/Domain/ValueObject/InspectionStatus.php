<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum InspectionStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectionStatus: string
{
  case DRAFT = 'draft';
  case SUBMITTED = 'submitted';
  case CLOSED = 'closed';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported inspection status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the inspection status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method isDraft.
   *
   * @since 1.0.0
   */
  public function isDraft(): bool
  {
    return self::DRAFT === $this;
  }

  /**
   * Method isClosed.
   *
   * @since 1.0.0
   */
  public function isClosed(): bool
  {
    return self::CLOSED === $this;
  }

  /**
   * Method isSubmitted.
   *
   * @since 1.0.0
   */
  public function isSubmitted(): bool
  {
    return self::SUBMITTED === $this;
  }

  public function label(): string
  {
    return match ($this) {
      self::DRAFT => 'Draft',
      self::SUBMITTED => 'Submitted',
      self::CLOSED => 'Closed',
    };
  }
  // #endregion
}
