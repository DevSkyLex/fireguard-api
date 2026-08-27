<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionChangeStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionChangeStatus: string
{
  case PROPOSED = 'proposed';
  case REJECTED = 'rejected';
  case APPLIED = 'applied';

  /**
   * Method isTerminal.
   *
   * `APPLIED` is reached only through the publication path and, once there,
   * an intervention change can never move again.
   *
   * @since 1.0.0
   *
   * @return bool the is terminal result
   */
  public function isTerminal(): bool
  {
    return self::APPLIED === $this;
  }
}
