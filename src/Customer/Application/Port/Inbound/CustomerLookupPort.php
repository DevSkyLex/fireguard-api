<?php

declare(strict_types=1);

namespace Customer\Application\Port\Inbound;

use Customer\Application\Contract\CustomerSnapshot;

/** Interface CustomerLookupPort. Unknown and foreign identifiers return the same null; archived identities remain readable. @category Port */
interface CustomerLookupPort
{
  public function find(string $customerId, string $organizationId): ?CustomerSnapshot;
}
