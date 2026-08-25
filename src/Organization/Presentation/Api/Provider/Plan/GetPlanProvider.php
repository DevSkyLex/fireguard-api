<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Plan;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Plan\GetPlan\{GetPlanQuery, GetPlanResult};
use Organization\Presentation\Api\Dto\Output\Plan\PlanOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException};

use function is_string;

/**
 * Provider GetPlanProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PlanOutput>
 */
final readonly class GetPlanProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetPlanProvider class.
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
   * Returns a single subscription plan by identifier.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PlanOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $planId = $uriVariables['id'] ?? null;
    if (!is_string($planId) || '' === $planId) {
      return null;
    }

    /** @var GetPlanResult $result */
    $result = $this->queryBus->ask(new GetPlanQuery($planId));

    return PlanOutput::fromResult($result);
  }
  // #endregion
}
