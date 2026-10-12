<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

/**
 * Interface FreshOrganizationMemberDirectoryPort
 *
 * Requires uncached scalar membership reads for current participation decisions before saved response disclosure.
 *
 * @category Port
 */
interface FreshOrganizationMemberDirectoryPort extends OrganizationMemberDirectoryPort
{
}
