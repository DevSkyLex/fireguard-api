<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\Tenant\DeleteTenant;

use Shared\Application\Message\CommandHandler;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Handler DeleteTenantHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteTenantHandler implements CommandHandler
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
    private readonly TenantRepositoryPort $tenantRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Deletes a tenant.
   *
   * @since 1.0.0
   *
   * @param DeleteTenantCommand $command the command
   *
   * @throws TenantNotFoundException if tenant is not found
   *
   * @return DeleteTenantResult the result
   */
  public function __invoke(DeleteTenantCommand $command): DeleteTenantResult
  {
    $tenantId = TenantId::fromString(value: $command->tenantId);
    $tenant = $this->tenantRepository->findById(id: $tenantId);

    if (null === $tenant) {
      throw TenantNotFoundException::withId(id: $command->tenantId);
    }

    $this->tenantRepository->delete(id: $tenantId);

    return new DeleteTenantResult(tenantId: (string) $tenant->id());
  }
  // #endregion
}
