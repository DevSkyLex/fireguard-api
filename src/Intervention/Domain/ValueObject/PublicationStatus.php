<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum PublicationStatus.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum PublicationStatus: string
{
  /**
   * Case PENDING
   */
  case PENDING = 'pending';

  /**
   * Case PROCESSING
   */
  case PROCESSING = 'processing';

  /**
   * Case COMPLETED
   */
  case COMPLETED = 'completed';

  /**
   * Case FAILED
   */
  case FAILED = 'failed';

  /**
   * Method isTerminal.
   *
   * `COMPLETED` is the only status a publication can never leave.
   *
   * @since 1.0.0
   *
   * @return bool the is terminal result
   */
  public function isTerminal(): bool
  {
    return self::COMPLETED === $this;
  }
}
