<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Equipment\Application\Port\Inbound\FreshEquipmentScopePort;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException};
use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, FreshOrganizationMemberDirectoryPort};
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;

use function array_is_list;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

/** Reads current owner rows and applies the existing participant policy before receipt body disclosure. @category Adapter */
final readonly class FreshInterventionScopeAdapter implements FreshInterventionScopePort
{
  private InterventionMemberPolicy $members;

  public function __construct(private Connection $connection, private FreshOrganizationAuthorizationPort $authorization, FreshOrganizationMemberDirectoryPort $members, private FreshEquipmentScopePort $equipment)
  {
    $this->members = new InterventionMemberPolicy($members);
  }

  public function organizationId(string $interventionId): ?string
  {
    return $this->context($interventionId)?->organizationId;
  }

  public function context(string $interventionId): ?InterventionAssignmentContext
  {
    $row = $this->connection->fetchAssociative('SELECT id, organization_id, status, responsible_id, participants FROM interventions WHERE id = :id', ['id' => $interventionId]);
    if (false === $row) {
      return null;
    }

    return new InterventionAssignmentContext($this->string($row, 'id'), $this->string($row, 'organization_id'), $this->string($row, 'status'), $this->nullableString($row, 'responsible_id'), $this->participants($row['participants'] ?? null));
  }

  public function mutationPermission(string $interventionId, string $actorId, string $organizationId): string
  {
    $context = $this->requireContext($organizationId, $interventionId);
    if (!$this->authorization->isMemberOf($actorId, $organizationId) || in_array($context->status, ['submitted', 'published', 'abandoned'], true)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if ('draft' !== $context->status) {
      $this->memberCheck(fn () => $this->members->assertCanExecuteIntervention($organizationId, $actorId, $context->responsibleId, $context->participants));
    }

    return 'draft' === $context->status ? 'organization.interventions.plan' : 'organization.interventions.execute';
  }

  public function assertExecutionAccess(string $organizationId, string $interventionId, string $actorId, ?string $workItemId = null): void
  {
    $context = $this->requireContext($organizationId, $interventionId);
    if (!$this->authorization->hasPermission($actorId, $organizationId, 'organization.interventions.execute')) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if (null === $workItemId) {
      $this->memberCheck(fn () => $this->members->assertCanExecuteIntervention($organizationId, $actorId, $context->responsibleId, $context->participants));

      return;
    }
    $row = $this->connection->fetchAssociative('SELECT assignee_id FROM intervention_work_items WHERE id = :id AND intervention_id = :parent', ['id' => $workItemId, 'parent' => $interventionId]);
    if (false === $row) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $this->memberCheck(fn () => $this->members->assertCanExecuteWorkItem($organizationId, $actorId, $context->responsibleId, $context->participants, $this->nullableString($row, 'assignee_id')));
  }

  public function assertResponsibleAccess(string $organizationId, string $interventionId, string $actorId): void
  {
    $context = $this->requireContext($organizationId, $interventionId);
    $this->memberCheck(fn () => $this->members->assertResponsible($organizationId, $actorId, $context->responsibleId));
  }

  public function assertInventoryAccess(string $organizationId, string $interventionId, ?string $workItemId, ?string $equipmentId, string $actorId): void
  {
    $this->assertExecutionAccess($organizationId, $interventionId, $actorId, $workItemId);
    if (null !== $equipmentId && $this->equipment->organizationId($equipmentId) !== $organizationId) {
      throw new IdempotencyDeniedException('access_denied');
    }
    if (null === $workItemId || null === $equipmentId) {
      return;
    }
    $target = $this->connection->fetchOne('SELECT target FROM intervention_work_items WHERE id = :id AND intervention_id = :parent', ['id' => $workItemId, 'parent' => $interventionId]);
    $resolved = null;
    if (is_string($target) && 1 === preg_match('#^/api/equipment/([^/]+)$#D', $target, $match)) {
      $resolved = $match[1];
    } elseif (is_string($target)) {
      $decoded = json_decode($target, true);
      $resolved = is_array($decoded) && is_string($decoded['equipmentId'] ?? null) ? $decoded['equipmentId'] : null;
    }
    if ($resolved !== $equipmentId) {
      throw new IdempotencyDeniedException('access_denied');
    }
  }

  public function assertRetainedWorkItemExecutionAccess(string $organizationId, string $interventionId, string $actorId, ?string $originalAssigneeId): void
  {
    $context = $this->requireContext($organizationId, $interventionId);
    if (!$this->authorization->hasPermission($actorId, $organizationId, 'organization.interventions.execute')) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $this->memberCheck(fn () => $this->members->assertCanExecuteWorkItem($organizationId, $actorId, $context->responsibleId, $context->participants, $originalAssigneeId));
  }

  public function assertRetainedInterventionExecutionAccess(string $organizationId, string $actorId, ?string $originalResponsibleId, array $originalParticipants, ?string $originalAssigneeId = null): void
  {
    if (!$this->authorization->hasPermission($actorId, $organizationId, 'organization.interventions.execute')) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $this->memberCheck(fn () => $this->members->assertCanExecuteWorkItem($organizationId, $actorId, $originalResponsibleId, $originalParticipants, $originalAssigneeId));
  }

  private function requireContext(string $organizationId, string $interventionId): InterventionAssignmentContext
  {
    $context = $this->context($interventionId);
    if (null === $context || $context->organizationId !== $organizationId) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $context;
  }

  /**
   * @param callable(): void $check existing owner participant policy
   */
  private function memberCheck(callable $check): void
  {
    try {
      $check();
    } catch (InterventionAccessDeniedException|InterventionConflictException) {
      throw new IdempotencyDeniedException('access_denied');
    }
  }

  /**
   * @param array<string, mixed> $row
   */
  private function string(array $row, string $key): string
  {
    $value = $row[$key] ?? null;
    if (!is_string($value) || '' === $value) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $value;
  }

  /**
   * @param array<string, mixed> $row
   */
  private function nullableString(array $row, string $key): ?string
  {
    $value = $row[$key] ?? null;
    if (null !== $value && !is_string($value)) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $value;
  }

  /**
   * @return list<string>
   */
  private function participants(mixed $value): array
  {
    $decoded = is_string($value) ? json_decode($value, true, 16, JSON_THROW_ON_ERROR) : $value;
    if (!is_array($decoded) || !array_is_list($decoded)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    foreach ($decoded as $participant) {
      if (!is_string($participant)) {
        throw new IdempotencyDeniedException('access_denied');
      }
    }

    /** @var list<string> $decoded validated finite member identifiers */

    return $decoded;
  }
}
