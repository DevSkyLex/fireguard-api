<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Query\GetTenant;

use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Handler GetTenantHandler
 * @final
 *
 * Handles getting a tenant.
 *
 * @category Handler
 * @package Tenant\Application\UseCase\Query\GetTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetTenantHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantRepositoryPort $tenantRepository The tenant repository.
   */
  public function __construct(
    private TenantRepositoryPort $tenantRepository,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the GetTenantQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GetTenantQuery $query The query to handle.
   *
   * @return GetTenantResult The result.
   *
   * @throws TenantNotFoundException If tenant is not found.
   */
  public function __invoke(GetTenantQuery $query): GetTenantResult
  {
    $tenantId = TenantId::fromString(value: $query->tenantId);
    $tenant = $this->tenantRepository->findById(id: $tenantId);

    if ($tenant === null) {
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
  //#endregion
}
