<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\PurgeSessionLocations;

use Shared\Application\Message\CommandMessage;

/**
 * Operator-only erasure of retained session locations.
 *
 * @category Command
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PurgeSessionLocationsCommand implements CommandMessage
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string|null $userId authorized account erasure, or revoked sessions only
   */
  public function __construct(public ?string $userId = null)
  {
  }
  // #endregion
}
