<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

/**
 * Port InterventionDraftResourcesPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionDraftResourcesPort
{
  // #region Methods
  /**
   * Resolves all draft identities that an intervention discard would remove.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the discarded intervention
   *
   * @return list<string> canonical draft resource IRIs
   */
  public function draftResourceIris(string $interventionId): array;
  // #endregion
}
