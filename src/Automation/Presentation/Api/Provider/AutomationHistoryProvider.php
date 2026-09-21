<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Automation\Application\Contract\Trigger\AutomationTriggers;
use Automation\Application\UseCase\Query\ListAutomationAttempts\{ListAutomationAttemptsQuery, ListAutomationAttemptsResult};
use Automation\Presentation\Api\Dto\Output\{AutomationAttemptOutput, AutomationPolicyOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_map;
use function is_int;
use function is_string;

/** @implements ProviderInterface<object> */
final readonly class AutomationHistoryProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor)
  {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organization = $uriVariables['organizationId'] ?? null;
    if (!is_string($organization)) {
      throw new BadRequestHttpException('Organization is required.');
    }
    $policy = 'automation_get_policy' === $operation->getName();
    $page = $operation->getParameters()?->get('page')?->getValue() ?? 1;
    $size = $operation->getParameters()?->get('itemsPerPage')?->getValue() ?? 30;
    $page = is_int($page) ? $page : 1;
    $size = is_int($size) ? $size : 30;
    $id = $uriVariables['id'] ?? null;
    /** @var ListAutomationAttemptsResult $result */
    $result = $this->queries->ask(new ListAutomationAttemptsQuery($actor, $organization, $page, $policy ? 0 : $size, is_string($id) ? $id : null));
    if ($policy) {
      return new AutomationPolicyOutput($organization, AutomationTriggers::RULE_AUTO_CREATE_INTERVENTION_ON_CRITICAL_NC, $result->enabled, $result->canManage);
    }
    if (is_string($id)) {
      return AutomationAttemptOutput::fromView($result->attempts[0]);
    }

    return new TraversablePaginator(new ArrayIterator(array_map(AutomationAttemptOutput::fromView(...), $result->attempts)), $page, $size, $result->total);
  }
}
