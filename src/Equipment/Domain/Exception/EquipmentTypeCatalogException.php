<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

/**
 * Exception EquipmentTypeCatalogException.
 *
 * Carries a stable catalog failure without transport status or persistence details.
 *
 * @category Exception
 */
final class EquipmentTypeCatalogException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $reason stable catalog failure code
   * @param string $message caller-facing explanation
   *
   * @return void
   */
  public function __construct(
    public readonly string $reason,
    string $message,
  ) {
    parent::__construct($message);
  }
  // #endregion
}
