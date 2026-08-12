<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationPermissionOutput, OrganizationRoleOutput};
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function array_map;
use function array_slice;
use function count;
use function is_string;

/**
 * Provider ListOrganizationRolesProvider.
 *
 * `ListOrganizationRolesQuery`/`ListOrganizationRolesHandler` support real
 * DB-level pagination, but not search or sort — unlike
 * {@see ListOrganizationMembersProvider}, this provider cannot push `search`/
 * `order` down to the repository, so it deliberately keeps asking for the
 * FULL, unpaginated role list (`ListOrganizationRolesQuery::$pagination` left
 * `null`) and applies `CollectionSearcher`/`CollectionSorter` in-memory, the
 * same as before. What changes is that pagination is now real: the
 * filtered+sorted array is sliced against the requested `page`/
 * `itemsPerPage` and `totalItems` reflects the FILTERED count — never the
 * organization's raw role count — so a second page actually returns the
 * next rows instead of repeating (or silently ignoring) the first page. A
 * role list is small per organization, so this in-memory approach is
 * deliberate and bounded, not a scalability compromise.
 *
 * @category Provider
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationRoleOutput>
 */
final readonly class ListOrganizationRolesProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The query bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerQueryBusAdapter::ask()`), so a
   * direct `catch (OrganizationNotFoundException)` around `queryBus->ask()`
   * alone would never match at runtime — this trait walks the wrapped
   * exception's `getPrevious()`/`HandlerFailedException` chain to find the
   * real domain exception underneath.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationRolesProvider class.
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
   * @since 2.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return TraversablePaginator<OrganizationRoleOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return new TraversablePaginator(new ArrayIterator([]), 1, 30, 0);
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.roles.read')) {
      throw new AccessDeniedHttpException('Missing Organization.roles.read permission.');
    }

    try {
      // Deliberately unpaginated — see the class docblock for why.
      /** @var ListOrganizationRolesResult $result */
      $result = $this->queryBus->ask(new ListOrganizationRolesQuery($organizationId));
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
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
      $output->memberCount = $role->memberCount;
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'isSystem', 'createdAt'], 'name');
    $outputs = CollectionSorter::sort($outputs, $sorting);

    $filteredTotal = count($outputs);
    $pagination = PaginationExtractor::fromContext($context);
    $page = array_slice($outputs, $pagination->offset, $pagination->itemsPerPage);

    return new TraversablePaginator(
      traversable: new ArrayIterator($page),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $filteredTotal,
    );
  }
  // #endregion
}
