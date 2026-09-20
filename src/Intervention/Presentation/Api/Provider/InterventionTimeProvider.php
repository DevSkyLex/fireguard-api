<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Query\Time\ListTimeEntries\{ListTimeEntriesQuery, ListTimeEntriesResult};
use Intervention\Presentation\Api\Dto\Output\TimeJournalOutput;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

use function is_string;

/**
 * InterventionTimeProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TimeJournalOutput>
 */
final readonly class InterventionTimeProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

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
   * Dispatches a task-journal read under the caller's contribution permissions.
   *
   * @since 1.0.0
   *
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return TimeJournalOutput authorized task journal and its independent entry revisions
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TimeJournalOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $taskId = is_string($uriVariables['taskId'] ?? null) ? $uriVariables['taskId'] : '';

    try {
      /** @var ListTimeEntriesResult $result */
      $result = $this->queries->ask(new ListTimeEntriesQuery($user->getId(), $taskId));
    } catch (Throwable $error) {
      throw $this->mapWorkflowException($error);
    }

    return new TimeJournalOutput($taskId, $result->entries);
  }
}
