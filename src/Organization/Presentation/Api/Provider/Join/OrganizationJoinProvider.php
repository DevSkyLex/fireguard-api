<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Join;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use LogicException;
use Organization\Application\UseCase\Query\Join\ReadOrganizationJoin\{ReadOrganizationJoinQuery, ReadOrganizationJoinResult};
use Organization\Presentation\Api\Service\Join\OrganizationJoinOutputAssembler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function is_string;

/**
 * Provider OrganizationJoinProvider.
 *
 * @implements ProviderInterface<object>
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   *
   * @param QueryBusPort $queries query bus
   * @param Security $security current actor
   * @param OrganizationJoinOutputAssembler $assembler output contracts
   * @param RateLimiterFactory $limiter discovery budget
   */
  public function __construct(private QueryBusPort $queries, private Security $security, private OrganizationJoinOutputAssembler $assembler, #[Autowire(service: 'limiter.organization_join')] private RateLimiterFactory $limiter)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param Operation $operation metadata
   * @param array<string,mixed> $uriVariables path variables
   * @param array<string,mixed> $context serialization context
   *
   * @return object output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $actor = $this->security->getUser();
    if (!$actor instanceof SecurityUser) {
      throw new AccessDeniedHttpException();
    }
    if (!$this->limiter->create($actor->getId())->consume()->isAccepted()) {
      throw new TooManyRequestsHttpException();
    }
    $action = $operation->getExtraProperties()['join_action'] ?? null;
    if (!is_string($action)) {
      throw new LogicException('Missing join action.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $result = $this->queries->ask(new ReadOrganizationJoinQuery($action, $actor->getId(), is_string($organizationId) ? $organizationId : null));
    if (!$result instanceof ReadOrganizationJoinResult) {
      throw new LogicException('Unexpected join query result.');
    }

    return $this->assembler->assemble($operation, $result->data);
  }
}
