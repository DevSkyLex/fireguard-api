<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionChangeStatus
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionChangeStatus: string
{
  // #region Cases
  /**
   * Case PROPOSED
   *
   * The proposed change is awaiting a decision.
   */
  case PROPOSED = 'proposed';

  /**
   * Case REJECTED
   *
   * The proposed change was declined.
   */
  case REJECTED = 'rejected';

  /**
   * Case APPLIED
   *
   * The proposed change was applied through publication.
   */
  case APPLIED = 'applied';
  // #endregion

  // #region Methods
  /**
   * Method isTerminal
   *
   * `APPLIED` is reached only through the publication path and, once there,
   * an intervention change can never move again.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool whether the change has been applied
   */
  public function isTerminal(): bool
  {
    return self::APPLIED === $this;
  }
  // #endregion
}
