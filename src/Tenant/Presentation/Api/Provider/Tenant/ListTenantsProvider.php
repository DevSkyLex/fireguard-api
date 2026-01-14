<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Provider\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult;
use Tenant\Application\UseCase\Query\Tenant\ListTenants\{ListTenantsQuery, ListTenantsResult};
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;

use function array_map;

/**
 * Provider ListTenantsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TenantOutput>
 */
final readonly class ListTenantsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return list<TenantOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    /** @var ListTenantsResult $result */
    $result = $this->queryBus->ask(query: new ListTenantsQuery());

    return array_map(
      callback: function (GetTenantResult $tenant): TenantOutput {
        $output = new TenantOutput();
        $output->id = $tenant->tenantId;
        $output->name = $tenant->name;
        $output->isActive = $tenant->isActive;
        $output->accessTokenTtl = $tenant->settings->accessTokenTtl;
        $output->refreshTokenTtl = $tenant->settings->refreshTokenTtl;
        $output->requirePkce = $tenant->settings->requirePkce;
        $output->createdAt = $tenant->createdAt->format('c');

        return $output;
      },
      array: $result->tenants,
    );
  }
  // #endregion
}
