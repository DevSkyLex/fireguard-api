<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Event;

/**
 * Interface IntegrationEventConverterPort
 *
 * Publishes an owner's historical snapshot without exposing its private Domain types to consumers.
 *
 * @category Outbound Port
 */
interface IntegrationEventConverterPort
{
  // #region Methods
  /**
   * Method convert
   *
   * Returns a public snapshot for an exact supported legacy class, preserving its persisted data.
   *
   * @access public
   *
   * @param object $event deserialized event to inspect
   *
   * @return ?object public snapshot, or null when this owner does not recognize the event
   */
  public function convert(object $event): ?object;
  // #endregion
}
