<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Event;

/**
 * Interface IntegrationEventSnapshotPort
 *
 * Copies an owner-approved legacy snapshot into its equivalent public contract without new timestamps.
 *
 * @category Outbound Port
 */
interface IntegrationEventSnapshotPort
{
  // #region Methods
  /**
   * Method copy
   *
   * Preserves properties and scalarizes identifier values explicitly represented as strings by the contract.
   *
   * @access public
   *
   * @template T of object
   *
   * @param object $event original deserialized snapshot
   * @param class-string<T> $contract owner-approved public counterpart
   *
   * @return T the equivalent public snapshot
   */
  public function copy(object $event, string $contract): object;
  // #endregion
}
