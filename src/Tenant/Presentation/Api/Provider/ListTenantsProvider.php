<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Model\Tenant;
use Tenant\Presentation\Api\Dto\TenantOutput;

/**
 * Provider ListTenantsProvider
 * @final
 *
 * API Platform provider for listing tenants.
 *
 * @category Provider
 * @package Tenant\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TenantOutput>
 */
final readonly class ListTenantsProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantRepositoryPort $tenantRepository The tenant repository.
   * @param Security $security The security service.
   */
  public function __construct(
    private TenantRepositoryPort $tenantRepository,
    private Security $security,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return list<TenantOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    if ($this->security->getUser() === null) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $tenants = $this->tenantRepository->findAll();

    return array_map(
      callback: function (Tenant $tenant): TenantOutput {
        $output = new TenantOutput();
        $output->id = (string) $tenant->id();
        $output->name = (string) $tenant->name();
        $output->isActive = $tenant->isActive();
        $output->accessTokenTtl = $tenant->settings()->accessTokenTtl;
        $output->refreshTokenTtl = $tenant->settings()->refreshTokenTtl;
        $output->requirePkce = $tenant->settings()->requirePkce;
        $output->createdAt = $tenant->createdAt()->format('c');
        return $output;
      },
      array: $tenants,
    );
  }
  //#endregion
}
