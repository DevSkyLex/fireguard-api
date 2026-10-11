<?php

declare(strict_types=1);

namespace Facility\Application\Port\Inbound;

use Facility\Application\Contract\Facility\CanonicalFacilityView;

/**
 * Port FacilityMutationAuthorizationPort.
 *
 * Authorizes a published or intervention-owned canonical mutation.
 *
 * @category Port
 */
interface FacilityMutationAuthorizationPort
{
  // #region Methods
  /**
   * Method assertCanMutate.
   */
  public function assertCanMutate(string $userId, CanonicalFacilityView $view, bool $forUpdate = true): void;
  // #endregion
}
