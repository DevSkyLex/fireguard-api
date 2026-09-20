<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Workload\Application\UseCase\Query\Capacity\GetCapacity\{GetCapacityQuery, GetCapacityResult};
use Workload\Presentation\Api\Dto\Output\CapacityOutput;

use function is_string;

/**
 * CapacityProvider.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CapacityOutput>
 */
final readonly class CapacityProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   *
   * @param QueryBusPort $queries dispatches read use cases through the query bus
   * @param Security $security resolves the authenticated account at the HTTP boundary
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }

  /**
   * Dispatches an authorized read of capacity history and availability exceptions.
   *
   * @since 1.0.0
   *
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return CapacityOutput historical capacity weeks and dated exceptions
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CapacityOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId)) {
      throw new BadRequestHttpException('Organization is required.');
    }
    /** @var GetCapacityResult $result */
    $result = $this->queries->ask(new GetCapacityQuery($user->getId(), $organizationId, is_string($uriVariables['memberId'] ?? null) ? $uriVariables['memberId'] : null));

    return new CapacityOutput($organizationId, $result->configuration);
  }
}
