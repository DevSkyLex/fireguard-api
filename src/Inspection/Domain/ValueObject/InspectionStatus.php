<?php

declare(strict_types=1);

namespace Inspection\Domain\ValueObject;

use function array_column;

/**
 * Enum InspectionStatus
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InspectionStatus: string
{
  // #region Cases
  /**
   * Case DRAFT
   *
   * The inspection is being prepared and has not been submitted.
   */
  case DRAFT = 'draft';

  /**
   * Case SUBMITTED
   *
   * The inspection was submitted and may be closed.
   */
  case SUBMITTED = 'submitted';

  /**
   * Case CLOSED
   *
   * The inspection lifecycle is complete.
   */
  case CLOSED = 'closed';

  /**
   * Case CANCELLED
   *
   * The inspection was cancelled before completion.
   */
  case CANCELLED = 'cancelled';
  // #endregion

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
   * Method isDraft
   *
   * Indicates whether the inspection is still being prepared.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true when the inspection is a draft
   */
  public function isDraft(): bool
  {
    return self::DRAFT === $this;
  }

  /**
   * Method isClosed
   *
   * Indicates whether the inspection lifecycle is complete.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true when the inspection is closed
   */
  public function isClosed(): bool
  {
    return self::CLOSED === $this;
  }

  /**
   * Method isSubmitted
   *
   * Indicates whether the inspection has been submitted for completion.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true when the inspection is submitted
   */
  public function isSubmitted(): bool
  {
    return self::SUBMITTED === $this;
  }

  /**
   * Method isCancelled
   *
   * Indicates whether the inspection was cancelled.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool true when the inspection is cancelled
   */
  public function isCancelled(): bool
  {
    return self::CANCELLED === $this;
  }
  // #endregion
}
