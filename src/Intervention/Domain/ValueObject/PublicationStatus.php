<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum PublicationStatus
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum PublicationStatus: string
{
  // #region Cases
  /**
   * Case PENDING
   *
   * The publication has been created but has not started processing.
   */
  case PENDING = 'pending';

  /**
   * Case PROCESSING
   *
   * A worker is currently processing the publication.
   */
  case PROCESSING = 'processing';

  /**
   * Case COMPLETED
   *
   * Publication finished successfully and cannot transition further.
   */
  case COMPLETED = 'completed';

  /**
   * Case FAILED
   *
   * Processing failed before the publication completed.
   */
  case FAILED = 'failed';
  // #endregion

  // #region Methods
  /**
   * Method isTerminal
   *
   * `COMPLETED` is the only status a publication can never leave.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool whether the publication has completed
   */
  public function isTerminal(): bool
  {
    return self::COMPLETED === $this;
  }
  // #endregion
}
