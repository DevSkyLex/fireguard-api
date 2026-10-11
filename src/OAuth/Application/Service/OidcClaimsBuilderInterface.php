<?php

declare(strict_types=1);

namespace OAuth\Application\Service;

use OAuth\Application\Port\Outbound\Internal\User\OidcClaimsBuilderPort;

/**
 * Compatibility interface for the owner-local claims builder.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OidcClaimsBuilderInterface extends OidcClaimsBuilderPort
{
}
