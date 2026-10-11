<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Event;

/**
 * Interface IntegrationEventDecoderPort
 *
 * Normalizes legacy payloads before dispatch while leaving current public contracts unchanged.
 *
 * @category Outbound Port
 */
interface IntegrationEventDecoderPort
{
  // #region Methods
  /**
   * Method decode
   *
   * Converts a recognized historical snapshot; current or owner-internal events pass through.
   *
   * @access public
   *
   * @param object $event deserialized event payload
   *
   * @return object the public or unchanged event
   */
  public function decode(object $event): object;
  // #endregion
}
