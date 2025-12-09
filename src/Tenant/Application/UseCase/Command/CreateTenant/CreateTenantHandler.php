<?php

declare(strict_types=1);

namespace Tenant\Application\UseCase\Command\CreateTenant;

use Shared\Application\Factory\UuidFactory;

use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Domain\ValueObject\TenantName;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Handler CreateTenantHandler
 * @final
 *
 * Handles tenant creation.
 *
 * @category Handler
 * @package Tenant\Application\UseCase\Command\CreateTenant
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateTenantHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantRepositoryPort $tenantRepository The tenant repository.
   * @param UuidFactory $uuidFactory The UUID factory.
   */
  public function __construct(
    private TenantRepositoryPort $tenantRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the CreateTenantCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CreateTenantCommand $command The command to handle.
   *
   * @return CreateTenantResult The result.
   */
  public function __invoke(CreateTenantCommand $command): CreateTenantResult
  {
    $tenantId = $this->uuidFactory->create(TenantId::class);

    $tenant = Tenant::create(
      id: $tenantId,
      name: new TenantName(value: $command->name),
      settings: $command->settings ?? new TenantSettings(),
    );

    $this->tenantRepository->save(tenant: $tenant);

    return new CreateTenantResult(
      tenantId: (string) $tenantId,
    );
  }
  //#endregion
}
