<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Onboarding;

use Organization\Application\Contract\Onboarding\OnboardingOrganization;
use Organization\Application\Port\Inbound\OrganizationOnboardingDirectoryPort;
use Organization\Application\UseCase\Command\Organization\DeleteOrganization\DeleteOrganizationCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};

use function array_map;
use function array_values;

/**
 * Class OrganizationOnboardingDirectoryAdapter
 *
 * Keeps organization queries and archive messages private while publishing only required facts.
 *
 * @category Adapter
 */
final readonly class OrganizationOnboardingDirectoryAdapter implements OrganizationOnboardingDirectoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param QueryBusPort $queryBus canonical organization reads
   * @param CommandBusPort $commandBus canonical archive command
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queryBus, private CommandBusPort $commandBus)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function organizationsForUser(string $userId): array
  {
    /** @var PaginatedResult<GetOrganizationResult> $result */
    $result = $this->queryBus->ask(new ListUserOrganizationsQuery($userId));

    return array_map(static fn (GetOrganizationResult $organization): OnboardingOrganization => new OnboardingOrganization(
      id: $organization->id,
      name: $organization->name,
      slug: $organization->slug,
      ownerUserId: $organization->ownerUserId,
      createdByUserId: $organization->createdByUserId,
      isActive: $organization->isActive,
      createdAt: $organization->createdAt,
    ), array_values($result->items));
  }

  /**
   * {@inheritdoc}
   */
  public function archive(string $organizationId, string $slugConfirmation): void
  {
    $this->commandBus->dispatch(new DeleteOrganizationCommand(organizationId: $organizationId, slugConfirmation: $slugConfirmation));
  }
  // #endregion
}
