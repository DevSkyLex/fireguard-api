<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\{ListUserOrganizationsQuery, ListUserOrganizationsResult};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var ListUserOrganizationsResult $result */
    $result = $this->queryBus->ask(new ListUserOrganizationsQuery($user->getId()));

    $outputs = [];
    foreach ($result->organizations as $organization) {
      $output = new OrganizationOutput();
      $output->id = $organization->id;
      $output->name = $organization->name;
      $output->slug = $organization->slug;
      $output->ownerUserId = $organization->ownerUserId;
      $output->createdByUserId = $organization->createdByUserId;
      $output->status = $organization->status;
      $output->isActive = $organization->isActive;
      $output->createdAt = $organization->createdAt->format('c');
      $output->updatedAt = $organization->updatedAt->format('c');
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
