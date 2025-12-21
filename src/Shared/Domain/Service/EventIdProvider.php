<?php

declare(strict_types=1);

namespace Shared\Domain\Service;

use Shared\Domain\ValueObject\Uuid;

/**
 * Interface EventIdProvider.
 *
 * Provides unique identifiers for Domain Events.
 * This interface allows the Domain layer to generate event IDs
 * without depending on Infrastructure.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EventIdProvider
{
  // #region Methods
  /**
   * Method nextEventId.
   *
   * Generates a new unique event identifier.
   *
   * @since 1.0.0
   *
   * @return Uuid the new event ID
   */
  public function nextEventId(): Uuid;
  // #endregion
}
