<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Handler GetTenantHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantHandler implements \Shared\Application\Message\QueryHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param TenantRepositoryPort $tenantRepository the tenant repository
     */
    public function __construct(
        private TenantRepositoryPort $tenantRepository,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles the GetTenantQuery.
     *
     * @since 1.0.0
     *
     * @param GetTenantQuery $query the query to handle
     *
     * @return GetTenantResult the result
     *
     * @throws TenantNotFoundException if tenant is not found
     */
    public function __invoke(GetTenantQuery $query): GetTenantResult
    {
        $tenantId = TenantId::fromString(value: $query->tenantId);
        $tenant = $this->tenantRepository->findById(id: $tenantId);

        if (null === $tenant) {
            throw TenantNotFoundException::withId(id: $query->tenantId);
        }

        return new GetTenantResult(
            tenantId: (string) $tenant->id(),
            name: (string) $tenant->name(),
            settings: $tenant->settings(),
            isActive: $tenant->isActive(),
            createdAt: $tenant->createdAt(),
        );
    }
    // #endregion
}
