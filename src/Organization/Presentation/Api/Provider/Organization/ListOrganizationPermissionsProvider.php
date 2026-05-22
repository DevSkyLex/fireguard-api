<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions\{ListOrganizationPermissionsQuery, ListOrganizationPermissionsResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationPermissionOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Provider ListOrganizationPermissionsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationPermissionOutput>
 */
final readonly class ListOrganizationPermissionsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationPermissionsProvider class.
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
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationPermissionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return [];
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.read')) {
      throw new AccessDeniedHttpException('Missing organization.roles.read permission.');
    }

    /** @var ListOrganizationPermissionsResult $result */
    $result = $this->queryBus->ask(new ListOrganizationPermissionsQuery($organizationId));

    $outputs = [];
    foreach ($result->permissions as $permission) {
      $output = new OrganizationPermissionOutput();
      $output->name = $permission->name;
      $output->description = $permission->description;
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name', 'description']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'description'], 'name');

    return CollectionSorter::sort($outputs, $sorting);
  }
  // #endregion
}
