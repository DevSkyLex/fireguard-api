<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

/**
 * Query GetTenantQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantQuery
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $tenantId the tenant ID
     */
    public function __construct(
        public string $tenantId,
    ) {
    }
    // #endregion
}
