<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tenant\Application\UseCase\Query\GetTenant\GetTenantHandler;
use Tenant\Application\UseCase\Query\GetTenant\GetTenantQuery;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Presentation\Api\Dto\TenantOutput;

/**
 * Provider GetTenantProvider
 * @final
 *
 * API Platform provider for getting a tenant.
 *
 * @category Provider
 * @package Tenant\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TenantOutput>
 */
final readonly class GetTenantProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param GetTenantHandler $handler The query handler.
   * @param Security $security The security service.
   */
  public function __construct(
    private GetTenantHandler $handler,
    private Security $security,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TenantOutput
  {
    if ($this->security->getUser() === null) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $tenantId = $uriVariables['id'] ?? throw new NotFoundHttpException('Tenant ID is required.');

    try {
      $query = new GetTenantQuery(tenantId: $tenantId);
      $result = ($this->handler)($query);

      $output = new TenantOutput();
      $output->id = $result->tenantId;
      $output->name = $result->name;
      $output->isActive = $result->isActive;
      $output->accessTokenTtl = $result->settings->accessTokenTtl;
      $output->refreshTokenTtl = $result->settings->refreshTokenTtl;
      $output->requirePkce = $result->settings->requirePkce;
      $output->createdAt = $result->createdAt->format('c');

      return $output;
    } catch (TenantNotFoundException $e) {
      throw new NotFoundHttpException($e->getMessage(), $e);
    }
  }
  //#endregion
}
