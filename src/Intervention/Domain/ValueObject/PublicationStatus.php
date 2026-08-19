<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum PublicationStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum PublicationStatus: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case COMPLETED = 'completed';
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
