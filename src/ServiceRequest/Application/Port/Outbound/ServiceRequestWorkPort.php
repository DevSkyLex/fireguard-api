<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Port\Outbound;

use ServiceRequest\Application\Contract\Work\{ServiceRequestWorkLink, ServiceRequestWorkRequest};

/**
 * Interface ServiceRequestWorkPort
 *
 * Creates or explicitly links corrective repair work in the caller's main transaction.
 * The owner enforces intervention scope, grants and live task compatibility.
 *
 * @category Port
 */
interface ServiceRequestWorkPort
{
  /**
   * Acquires the owning intervention fence before target hierarchy and equipment locks, without writing work.
   */
  public function reserveSelection(ServiceRequestWorkRequest $request): void;

  public function createOrLink(ServiceRequestWorkRequest $request): ServiceRequestWorkLink;
}
