<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationQuota\{
  GetOrganizationQuotaQuery,
  GetOrganizationQuotaResult
};
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationQuotaItemOutput, OrganizationQuotaOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Provider GetOrganizationQuotaProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationQuotaOutput>
 */
final readonly class GetOrganizationQuotaProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetOrganizationQuotaProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Returns the per-resource quota usage for the organization.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationQuotaOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.read')) {
      throw new AccessDeniedHttpException('Missing organization.read permission.');
    }

    /** @var GetOrganizationQuotaResult $result */
    $result = $this->queryBus->ask(new GetOrganizationQuotaQuery($organizationId));

    $output = new OrganizationQuotaOutput();
    $output->organizationId = $organizationId;

    $items = [];
    foreach ($result->items as $item) {
      $itemOutput = new OrganizationQuotaItemOutput();
      $itemOutput->resource = $item['resource'];
      $itemOutput->used = $item['used'];
      $itemOutput->limit = $item['limit'];
      $items[] = $itemOutput;
    }
    $output->items = $items;

    return $output;
  }
  // #endregion
}
