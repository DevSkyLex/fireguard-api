<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Team;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Team\ListTeamMembers\{ListTeamMembersQuery, ListTeamMembersResult};
use Organization\Presentation\Api\Dto\Output\Team\TeamMemberOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

use function is_string;

/**
 * Provider ListTeamMembersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TeamMemberOutput>
 */
final readonly class ListTeamMembersProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListTeamMembersProvider class.
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
   * @return list<TeamMemberOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $teamId = $uriVariables['teamId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId || !is_string($teamId) || '' === $teamId) {
      return [];
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.teams.read')) {
      throw new AccessDeniedHttpException('Missing organization.teams.read permission.');
    }

    /** @var ListTeamMembersResult $result */
    $result = $this->queryBus->ask(new ListTeamMembersQuery($organizationId, $teamId));

    $outputs = [];
    foreach ($result->memberships as $membership) {
      $output = new TeamMemberOutput();
      $output->memberId = $membership->memberId;
      $output->role = $membership->role;
      $output->addedAt = $membership->addedAt->format('c');
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
