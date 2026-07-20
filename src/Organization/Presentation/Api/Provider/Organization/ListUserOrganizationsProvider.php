<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Organization\Domain\ValueObject\OrganizationSettings;
use Organization\Presentation\Api\Dto\Output\Organization\{OrganizationOutput, OrganizationSettingsOutput};
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_numeric;
use function is_string;
use function max;

/**
 * Provider ListUserOrganizationsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationOutput>
 */
final readonly class ListUserOrganizationsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUserOrganizationsProvider class.
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
   * Method provide.
   *
   * Provides a paginated list of organizations for the authenticated user.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return TraversablePaginator<OrganizationOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    /** @var PaginatedResult<GetOrganizationResult> $result */
    $result = $this->queryBus->ask(new ListUserOrganizationsQuery(
      userId: $user->getId(),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      status: isset($filters['status']) && is_string($filters['status']) && '' !== $filters['status'] ? $filters['status'] : null,
      search: SearchExtractor::fromContext($context),
      sorting: SortingExtractor::fromContext($context, ['name', 'slug', 'status', 'createdAt'], 'name'),
    ));

    $outputs = [];
    foreach ($result->items as $organization) {
      $output = new OrganizationOutput();
      $output->id = $organization->id;
      $output->name = $organization->name;
      $output->slug = $organization->slug;
      $output->ownerUserId = $organization->ownerUserId;
      $output->createdByUserId = $organization->createdByUserId;
      $output->status = $organization->status;
      $output->isActive = $organization->isActive;
      $output->description = $organization->description;
      $output->logoUrl = $organization->logoUrl;
      $output->memberCount = $organization->memberCount;
      $output->planId = $organization->planId;
      $output->planName = $organization->planName;
      $output->settings = OrganizationSettingsOutput::fromDomain($organization->settings ?? OrganizationSettings::default());
      $output->country = $organization->country;
      $output->legalType = $organization->legalType;
      $output->legalName = $organization->legalName;
      $output->registrationNumber = $organization->registrationNumber;
      $output->vatNumber = $organization->vatNumber;
      $output->createdAt = $organization->createdAt->format('c');
      $output->updatedAt = $organization->updatedAt->format('c');
      $outputs[] = $output;
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }
  // #endregion
}
