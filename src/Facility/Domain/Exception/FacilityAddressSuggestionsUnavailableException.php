<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception FacilityAddressSuggestionsUnavailableException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityAddressSuggestionsUnavailableException extends RuntimeException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * No address, response payload or provider URL is included in the error.
   *
   * @since 1.0.0
   */
  public function __construct()
  {
    parent::__construct('Address suggestions are temporarily unavailable.');
  }
  // #endregion
}
