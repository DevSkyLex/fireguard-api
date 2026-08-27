<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Provider\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Tenant\Application\UseCase\Query\Tenant\GetTenant\{GetTenantQuery, GetTenantResult};
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;

use function is_string;

/**
 * Provider GetTenantProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TenantOutput>
 */
final readonly class GetTenantProvider implements ProviderInterface
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
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TenantOutput
  {
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $tenantId = $uriVariables['id'] ?? null;

    if (!is_string($tenantId)) {
      throw new NotFoundHttpException('Tenant ID is required.');
    }

    /** @var GetTenantResult $result */
    $result = $this->queryBus->ask(query: new GetTenantQuery(tenantId: $tenantId));

    $output = new TenantOutput();
    $output->id = $result->tenantId;
    $output->name = $result->name;
    $output->isActive = $result->isActive;
    $output->accessTokenTtl = $result->settings->accessTokenTtl;
    $output->refreshTokenTtl = $result->settings->refreshTokenTtl;
    $output->requirePkce = $result->settings->requirePkce;
    $output->allowPublicClients = $result->settings->allowPublicClients;
    $output->allowedScopes = $result->settings->allowedScopes;
    $output->customIssuer = $result->settings->customIssuer;
    $output->createdAt = $result->createdAt->format('c');

    return $output;
  }
  // #endregion
}
