<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Plan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Plan\GetPlan\GetPlanResult;
use Organization\Application\UseCase\Query\Plan\ListPlans\{ListPlansQuery, ListPlansResult};
use Organization\Presentation\Api\Dto\Output\Plan\PlanOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;

/**
 * Provider ListPlansProvider.
 *
 * Lists subscription plans. Platform administrators see every plan; regular
 * users see only selectable plans for self-service.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PlanOutput>
 */
final readonly class ListPlansProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListPlansProvider class.
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
   * Returns the list of subscription plans.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<PlanOutput> the plan outputs
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $activeOnly = !$this->security->isGranted('ROLE_ADMIN');

    /** @var ListPlansResult $result */
    $result = $this->queryBus->ask(new ListPlansQuery(activeOnly: $activeOnly));

    return array_map(
      static fn (GetPlanResult $plan): PlanOutput => PlanOutput::fromResult($plan),
      $result->plans,
    );
  }
  // #endregion
}
