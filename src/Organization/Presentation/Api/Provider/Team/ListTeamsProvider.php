<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Team;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Team\ListTeams\{ListTeamsQuery, ListTeamsResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Team\TeamOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider ListTeamsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TeamOutput>
 */
final readonly class ListTeamsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamsProvider class.
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
   * @return list<TeamOutput>
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.teams.read')) {
      throw new AccessDeniedHttpException('Missing organization.teams.read permission.');
    }

    try {
      /** @var ListTeamsResult $result */
      $result = $this->queryBus->ask(new ListTeamsQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach ($result->teams as $team) {
      $output = new TeamOutput();
      $output->id = $team->id;
      $output->organizationId = $team->organizationId;
      $output->name = $team->name;
      $output->description = $team->description;
      $output->memberCount = $team->memberCount;
      $output->createdAt = $team->createdAt->format('c');
      $output->updatedAt = $team->updatedAt->format('c');
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'memberCount', 'createdAt'], 'name');

    return CollectionSorter::sort($outputs, $sorting);
  }
  // #endregion
}
