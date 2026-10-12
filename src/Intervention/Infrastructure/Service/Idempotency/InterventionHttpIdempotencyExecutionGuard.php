<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Idempotency;

use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;

use function array_is_list;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class InterventionHttpIdempotencyExecutionGuard
 *
 * Captures and rechecks owner-published execution evidence, including retained scope after deletion.
 *
 * @category Service
 */
final readonly class InterventionHttpIdempotencyExecutionGuard
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the same main connection and fresh owner capability as the replay authorizer.
   *
   * @access public
   *
   * @param Connection $connection Intervention-owned main connection
   * @param FreshInterventionScopePort $interventions current owner scope and execution policy
   *
   * @return void
   */
  public function __construct(private Connection $connection, private FreshInterventionScopePort $interventions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method authorize
   *
   * Rechecks saved execution evidence without recomputing a completed mutation's phase.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated replay request
   * @param string $organization authorized organization identifier
   * @param string $intervention original parent identifier
   * @param array<string, mixed>|null $row current target or null after deletion
   * @param array<string, string> $facts authenticated receipt evidence
   *
   * @return void
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, string $organization, string $intervention, ?array $row, array $facts): void
  {
    $workItem = $facts['executionWorkItemId'] ?? null;
    if (null === $row && 204 === $request->operation->statusCode) {
      $this->authorizeDeleted($request, $organization, $intervention, $workItem, $facts);
    } else {
      $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId, $workItem);
    }
  }

  /**
   * Method capture
   *
   * Retains the original team and task assignee only after current execution authorization succeeds.
   *
   * @access public
   *
   * @param HttpIdempotencyAuthorizationRequest $request first authenticated mutation request
   * @param string $organization authorized organization identifier
   * @param string $intervention current parent identifier
   * @param array<string, mixed>|null $row current target row
   * @param array<string, string> $facts prior ordinary authorization evidence
   *
   * @return array<string, string> evidence extended with original execution scope
   */
  public function capture(HttpIdempotencyAuthorizationRequest $request, string $organization, string $intervention, ?array $row, array $facts): array
  {
    $workItem = match ($request->operation->resourceKind) {
      'intervention_work_item' => $this->value($row, 'resource_id'),
      'intervention_change' => $this->value($row, 'work_item_id'),
      default => null,
    };
    $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId, $workItem);
    $facts['interventionPermission'] = 'organization.interventions.execute';
    $context = $this->interventions->context($intervention);
    if (null === $context || $context->organizationId !== $organization) {
      $this->deny();
    }
    $facts['executionResponsibleId'] = $context->responsibleId ?? '';
    $facts['executionParticipants'] = json_encode($context->participants, JSON_THROW_ON_ERROR);
    $facts['executionAssigneeId'] = '';
    if (null !== $workItem) {
      $facts['executionWorkItemId'] = $workItem;
      $assignee = $this->connection->fetchOne('SELECT assignee_id FROM intervention_work_items WHERE id = :id', ['id' => $workItem]);
      $facts['executionAssigneeId'] = is_string($assignee) ? $assignee : '';
    }

    return $facts;
  }

  /**
   * Method authorizeDeleted
   *
   * Uses signed original team evidence only when the current owner row no longer exists.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated delete replay
   * @param string $organization authorized original organization
   * @param string $intervention original parent identifier
   * @param string|null $workItem original task identifier when relevant
   * @param array<string, string> $facts authenticated original execution evidence
   *
   * @return void
   */
  private function authorizeDeleted(HttpIdempotencyAuthorizationRequest $request, string $organization, string $intervention, ?string $workItem, array $facts): void
  {
    $context = $this->interventions->context($intervention);
    if (null === $context) {
      if (!isset($facts['executionParticipants'], $facts['executionResponsibleId'], $facts['executionAssigneeId'])) {
        $this->deny();
      }
      $participants = $this->memberIds($facts['executionParticipants']);
      $this->interventions->assertRetainedInterventionExecutionAccess($organization, $request->principalId, $facts['executionResponsibleId'] ?: null, $participants, $facts['executionAssigneeId'] ?: null);
    } elseif (null !== $workItem) {
      if (!isset($facts['executionAssigneeId'])) {
        $this->deny();
      }
      $this->interventions->assertRetainedWorkItemExecutionAccess($organization, $intervention, $request->principalId, $facts['executionAssigneeId'] ?: null);
    } else {
      $this->interventions->assertExecutionAccess($organization, $intervention, $request->principalId);
    }
  }

  /**
   * Method memberIds
   *
   * Rejects malformed retained identities before consulting the owner execution policy.
   *
   * @access private
   *
   * @param string $json signed original team representation
   *
   * @return list<string> original member identifiers
   */
  private function memberIds(string $json): array
  {
    $members = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($members) || !array_is_list($members)) {
      $this->deny();
    }
    foreach ($members as $member) {
      if (!is_string($member)) {
        $this->deny();
      }
    }
    /** @var list<string> $members validated original signed identities */

    return $members;
  }

  /**
   * Method value
   *
   * Reads a non-empty identifier from an owner-local row.
   *
   * @access private
   *
   * @param array<string, mixed>|null $row optional owner target row
   * @param string $key scalar column name
   *
   * @return string|null current identifier or null when absent
   */
  private function value(?array $row, string $key): ?string
  {
    $value = $row[$key] ?? null;

    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * Method deny
   *
   * Fails closed before exposing a stored result.
   *
   * @access private
   *
   * @return never
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
