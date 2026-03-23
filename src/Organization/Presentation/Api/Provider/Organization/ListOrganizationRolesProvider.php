<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function array_map;
use function is_string;

/**
 * Provider ListOrganizationRolesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationRoleOutput>
 */
final readonly class ListOrganizationRolesProvider implements ProviderInterface
{
  // #region Constructor
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
   * @return list<OrganizationRoleOutput>
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
      throw new AccessDeniedHttpException('Missing Organization.roles.read permission.');
    }

    try {
      /** @var ListOrganizationRolesResult $result */
      $result = $this->queryBus->ask(new ListOrganizationRolesQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach ($result->roles as $role) {
      $output = new OrganizationRoleOutput();
      $output->id = $role->id;
      $output->organizationId = $role->organizationId;
      $output->name = $role->name;
      $output->permissions = array_map(
        static function (string $name): OrganizationPermissionOutput {
          $perm = new OrganizationPermissionOutput();
          $perm->name = $name;
          $perm->description = OrganizationPermissionCatalog::descriptionFor($name);

          return $perm;
        },
        $role->permissions,
      );
      $output->isSystem = $role->isSystem;
      $output->createdAt = $role->createdAt->format('c');
      $output->description = $role->description;
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'isSystem', 'createdAt'], 'name');

    return CollectionSorter::sort($outputs, $sorting);
  }
  // #endregion
}
