<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Intervention\Application\Service\{InterventionActionPolicy, InterventionMemberPolicy};
use Intervention\Domain\Service\{InterventionChangePolicy, InterventionMutabilityPolicy, InterventionTransitionPolicy};
use Intervention\Infrastructure\Adapter\Idempotency\{InterventionHttpIdempotencyOperationContributionAdapter, InterventionHttpIdempotencyReplayAuthorizationAdapter};
use Organization\Application\Port\Inbound\{FreshOrganizationAuthorizationPort, FreshOrganizationMemberDirectoryPort, FreshOrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;

/** Preserves the owner planning/execution matrix and requires original team evidence when deleted rows are gone. @category Unit Test */
final class InterventionHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  public function testPlanningOnlyTaskUpdateDoesNotRequireParticipationOrExecutionGrant(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'in_progress', 'intervention_id' => 'intervention', 'resource_id' => 'task']);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->expects(self::never())->method('assertExecutionAccess');
    $evidence = $this->createMock(HttpIdempotencyAuthorizationEvidencePort::class);
    $evidence->expects(self::once())->method('record')->with('user', 'org', ['organization.interventions.plan']);
    $adapter = $this->adapter($connection, $interventions, ['organization.interventions.plan'], $evidence);
    self::assertSame('org', $adapter->authorize($this->request('intervention_work_item_update', ['estimatedMinutes' => 90]), null)['organizationId']);
  }

  public function testMixedNonDraftInterventionEditRequiresBothPlanningAndExecution(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'in_progress', 'intervention_id' => 'intervention']);
    $adapter = $this->adapter($connection, $this->createStub(FreshInterventionScopePort::class), ['organization.interventions.plan']);
    $this->expectException(IdempotencyDeniedException::class);
    $adapter->authorize($this->request('intervention_update', ['name' => 'changed', 'participants' => []]), null);
  }

  public function testDeletedInterventionWithoutRetainedTeamProofFailsClosed(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $interventions = $this->createStub(FreshInterventionScopePort::class);
    $interventions->method('context')->willReturn(null);
    $request = $this->request('intervention_delete');
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection, $interventions, [])->authorize($request, new IdempotencyProof(['organizationId' => 'org', 'interventionId' => 'intervention', 'interventionPermission' => 'organization.interventions.execute'], new DateTimeImmutable('+1 day')));
  }

  public function testDeletedInterventionPassesSignedOriginalTeamToTheCurrentOwnerPolicy(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->method('context')->willReturn(null);
    $interventions->expects(self::once())->method('assertRetainedInterventionExecutionAccess')->with('org', 'user', 'responsible', ['participant'], null);
    $proof = new IdempotencyProof(['organizationId' => 'org', 'interventionId' => 'intervention', 'interventionPermission' => 'organization.interventions.execute', 'executionResponsibleId' => 'responsible', 'executionParticipants' => '["participant"]', 'executionAssigneeId' => ''], new DateTimeImmutable('+1 day'));
    self::assertSame($proof->metadata, $this->adapter($connection, $interventions, [])->authorize($this->request('intervention_delete'), $proof));
  }

  public function testCompletedTaskReplayRechecksAssignmentWithoutRecomputingItsMutablePhase(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'intervention', 'organization_id' => 'org', 'status' => 'published', 'intervention_id' => 'intervention', 'resource_id' => 'task']);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->expects(self::once())->method('assertExecutionAccess')->with('org', 'intervention', 'user', 'task');
    $proof = new IdempotencyProof(['organizationId' => 'org', 'interventionId' => 'intervention', 'interventionPermission' => 'organization.interventions.execute', 'executionWorkItemId' => 'task'], new DateTimeImmutable('+1 day'));
    self::assertSame($proof->metadata, $this->adapter($connection, $interventions, [])->authorize($this->request('intervention_work_item_update'), $proof));
  }

  /**
   * @param list<string> $permissions
   */
  private function adapter(Connection $connection, FreshInterventionScopePort $interventions, array $permissions, ?HttpIdempotencyAuthorizationEvidencePort $evidence = null): InterventionHttpIdempotencyReplayAuthorizationAdapter
  {
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $org): array => null === $proof ? ['organizationId' => $org ?? 'org'] : $proof->metadata);
    $authorization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $permission): bool => in_array($permission, $permissions, true));
    $members = $this->createStub(FreshOrganizationMemberDirectoryPort::class);
    $actions = new InterventionActionPolicy($authorization, new InterventionMemberPolicy($members), new InterventionTransitionPolicy(), new InterventionMutabilityPolicy(), new InterventionChangePolicy());

    return new InterventionHttpIdempotencyReplayAuthorizationAdapter($connection, $ordinary, $authorization, $interventions, $actions, $this->createStub(FreshOrganizationWorkforceDirectoryPort::class), $evidence);
  }

  /**
   * @param array<string, mixed> $fields
   */
  private function request(string $name, array $fields = []): HttpIdempotencyAuthorizationRequest
  {
    foreach (InterventionHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($name === $operation->name) {
        return new HttpIdempotencyAuthorizationRequest('user', 'tenant', $operation, '/api/interventions/resource', ['id' => 'resource'], ['method' => 204 === $operation->statusCode ? 'DELETE' : 'PATCH', 'body' => ['kind' => 'json', 'value' => ['kind' => 'object', 'fields' => $fields]]]);
      }
    }
    self::fail('The operation must be explicitly declared.');
  }
}
