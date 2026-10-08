<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Operation, QueryParameterInterface};
use ApiPlatform\State\{ParameterNotFound, ProviderInterface};
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Query\Time\GetTimeEntry\{GetTimeEntryQuery, GetTimeEntryResult};
use Intervention\Application\UseCase\Query\Time\ListTimeEntries\{ListTimeEntriesQuery, ListTimeEntriesResult};
use Intervention\Application\UseCase\Query\Time\ListTimeEntryVersions\{ListTimeEntryVersionsQuery, ListTimeEntryVersionsResult};
use Intervention\Presentation\Api\Dto\Output\{TimeEntryHistoryOutput, TimeEntryOutput, TimeJournalOutput};
use Intervention\Presentation\Api\Operation\InterventionTimeOperations;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function filter_var;
use function is_array;
use function is_bool;
use function is_string;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;
use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

/**
 * InterventionTimeProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TimeJournalOutput|TimeEntryHistoryOutput|TimeEntryOutput>
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
   * @return TimeJournalOutput|TimeEntryHistoryOutput|TimeEntryOutput authorized current entry, journal or history page
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TimeJournalOutput|TimeEntryHistoryOutput|TimeEntryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $taskId = is_string($uriVariables['taskId'] ?? null) ? $uriVariables['taskId'] : '';
    $filters = $this->filters($operation, $context);
    $itemsPerPage = $this->positiveInteger($filters['itemsPerPage'] ?? 30, 'itemsPerPage', 100);

    try {
      if (InterventionTimeOperations::GET === $operation->getName()) {
        $entryId = is_string($uriVariables['entryId'] ?? null) ? $uriVariables['entryId'] : '';
        /** @var GetTimeEntryResult $entry */
        $entry = $this->queries->ask(new GetTimeEntryQuery($user->getId(), $taskId, $entryId));

        return new TimeEntryOutput($entryId, $entry->entry);
      }
      if (InterventionTimeOperations::VERSIONS === $operation->getName()) {
        $entryId = is_string($uriVariables['entryId'] ?? null) ? $uriVariables['entryId'] : '';
        $before = isset($filters['beforeRevision']) ? $this->positiveInteger($filters['beforeRevision'], 'beforeRevision') : null;
        /** @var ListTimeEntryVersionsResult $history */
        $history = $this->queries->ask(new ListTimeEntryVersionsQuery($user->getId(), $taskId, $entryId, $before, $itemsPerPage));

        return new TimeEntryHistoryOutput($entryId, $history->versions, $history->totalItems, $history->itemsPerPage, $history->nextBeforeRevision);
      }
      /** @var ListTimeEntriesResult $result */
      $result = $this->queries->ask(new ListTimeEntriesQuery($user->getId(), $taskId, $this->positiveInteger($filters['page'] ?? 1, 'page'), $itemsPerPage, $this->boolean($filters['ownOnly'] ?? false, 'ownOnly')));
    } catch (Throwable $error) {
      throw $this->mapWorkflowException($error);
    }

    $next = $result->page * $result->itemsPerPage < $result->totalItems ? $result->page + 1 : null;

    return new TimeJournalOutput($taskId, $result->entries, $result->totalItems, $result->page, $result->itemsPerPage, $next);
  }

  /**
   * Method positiveInteger
   *
   * Rejects malformed and unbounded pagination before dispatch.
   *
   * @access private
   *
   * @param mixed $value raw query value
   * @param string $name parameter name
   * @param int $maximum upper bound
   *
   * @return int validated positive integer
   */
  private function positiveInteger(mixed $value, string $name, int $maximum = PHP_INT_MAX): int
  {
    $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);
    if (false === $parsed) {
      throw new BadRequestHttpException($name . ' must be a positive integer within its allowed range.');
    }

    return $parsed;
  }

  /**
   * Method boolean
   *
   * Rejects malformed scope selectors before dispatching a journal read.
   *
   * @access private
   *
   * @param mixed $value raw query value
   * @param string $name parameter name
   *
   * @return bool validated scope selector
   */
  private function boolean(mixed $value, string $name): bool
  {
    if (is_bool($value)) {
      return $value;
    }
    if (!is_string($value) || '' === trim($value)) {
      throw new BadRequestHttpException($name . ' must be a boolean.');
    }
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (null === $parsed) {
      throw new BadRequestHttpException($name . ' must be a boolean.');
    }

    return $parsed;
  }

  /**
   * Method filters
   *
   * Reads the journal's flat scope and pagination parameters from API Platform while retaining
   * context values for clients using the existing provider filter mechanism.
   *
   * @access private
   *
   * @param Operation $operation parsed API Platform parameter metadata
   * @param array<string, mixed> $context legacy provider filter values
   *
   * @return array<array-key, mixed> raw scope and pagination values for owner-local validation
   */
  private function filters(Operation $operation, array $context): array
  {
    $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
    foreach ($operation->getParameters() ?? [] as $key => $parameter) {
      if (!$parameter instanceof QueryParameterInterface) {
        continue;
      }
      $value = $parameter->getValue();
      if (!$value instanceof ParameterNotFound) {
        $filters[$key] = $value;
      }
    }

    return $filters;
  }
}
