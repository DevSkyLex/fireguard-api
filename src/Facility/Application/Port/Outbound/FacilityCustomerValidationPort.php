<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

/** Interface FacilityCustomerValidationPort. New assignments require an active customer in the same organization. @category Port */
interface FacilityCustomerValidationPort
{
  /**
   * @throws \Facility\Application\Contract\FacilityCustomerUnavailable
   */
  public function assertAssignable(string $customerId, string $organizationId): void;
}
