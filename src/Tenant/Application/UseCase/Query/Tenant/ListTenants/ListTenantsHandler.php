<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\Tenant\ListTenants;

use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult;
use Tenant\Domain\Model\Tenant\Tenant;

use function array_map;
use function count;

/**
 * Handler ListTenantsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListTenantsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListTenantsHandler class.
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
   * Handles the ListTenantsQuery.
   *
   * @since 1.0.0
   *
   * @param ListTenantsQuery $query the query to handle
   *
   * @return PaginatedResult<GetTenantResult> the result
   */
  public function __invoke(ListTenantsQuery $query): PaginatedResult
  {
    $tenants = $this->tenantRepository->findAll();

    $results = array_map(
      callback: fn (Tenant $tenant): GetTenantResult => new GetTenantResult(
        tenantId: (string) $tenant->id(),
        name: (string) $tenant->name(),
        settings: $tenant->settings(),
        isActive: $tenant->isActive(),
        createdAt: $tenant->createdAt(),
      ),
      array: $tenants,
    );

    $total = count($results);

    return new PaginatedResult(
      items: $results,
      total: $total,
      limit: $total,
      offset: 0,
    );
  }
  // #endregion
}
