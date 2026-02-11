<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{ListOrganizationMembersQuery, ListOrganizationMembersResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider ListOrganizationMembersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationMemberOutput>
 */
final readonly class ListOrganizationMembersProvider implements ProviderInterface
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
   * @return list<OrganizationMemberOutput>
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

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.read')) {
      throw new AccessDeniedHttpException('Missing Organization.members.read permission.');
    }

    try {
      /** @var ListOrganizationMembersResult $result */
      $result = $this->queryBus->ask(new ListOrganizationMembersQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach ($result->members as $member) {
      $output = new OrganizationMemberOutput();
      $output->id = $member->id;
      $output->organizationId = $member->organizationId;
      $output->userId = $member->userId;
      $output->isActive = $member->isActive;
      $output->joinedAt = $member->joinedAt->format('c');
      $output->roleIds = $member->roleIds;
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
