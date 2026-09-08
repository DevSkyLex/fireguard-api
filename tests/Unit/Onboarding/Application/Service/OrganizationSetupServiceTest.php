<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Application\Service;

use Onboarding\Application\Contract\Setup\{OrganizationSetupConflict, OrganizationSetupContext, OrganizationSetupOperation, OrganizationSetupSession};
use Onboarding\Application\Port\Outbound\OrganizationSetupRepositoryPort;
use Onboarding\Application\Service\OrganizationSetupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;

use function count;

/**
 * Durable organization setup recovery.
 *
 * @category Test
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSetupServiceTest extends TestCase
{
  #[Test]
  public function preparationReplacesUnconsumedItemsButPreservesCompletedResults(): void
  {
    $done = new OrganizationSetupOperation('invite_members', 'done', ['email' => 'done@example.com'], 'result-id');
    $pending = new OrganizationSetupOperation('invite_members', 'old', ['email' => 'typo@example.com']);
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->with('user', true)->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'invite_members', 'org', true, [$done, $pending]));
    $repository->expects(self::once())->method('saveOperations')->with('session', self::callback(static fn (array $operations): bool => 2 === count($operations) && $operations[0] instanceof OrganizationSetupOperation && $operations[1] instanceof OrganizationSetupOperation && 'done' === $operations[0]->itemKey && 'result-id' === $operations[0]->resourceId && 'corrected' === $operations[1]->itemKey));
    $this->service($repository)->prepare('user', 'session', 'invite_members', [['itemKey' => 'corrected', 'payload' => ['email' => 'correct@example.com']]]);
  }

  #[Test]
  public function replayFindsTheOriginalResultAfterItsStepWasConfirmed(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company'], 'created-id');
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'select_plan', 'created-id', true, [$operation]));
    $repository->expects(self::never())->method('saveOperations');
    $result = $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'org'), 'create_organization', null, ['name' => 'Company', 'slug' => null]);
    self::assertSame('created-id', $result->resourceId);
  }

  #[Test]
  public function changingThePayloadForACompletedKeyIsRejected(): void
  {
    $operation = new OrganizationSetupOperation('create_organization', 'org', ['name' => 'Company'], 'created-id');
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_organization', null, true, [$operation]));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Different company']]]);
  }

  #[Test]
  public function anotherUsersSessionCannotBeConsumed(): void
  {
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('own-session', 'attacker', 'in_progress', 'create_organization', null, true, []));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->begin(new OrganizationSetupContext('attacker', 'victim-session', 'org'), 'create_organization', null, ['name' => 'Company']);
  }

  #[Test]
  public function joinedOrganizationCannotConsumeCreatorOperations(): void
  {
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_first_facility', 'created-org', true, []));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'facility'), 'create_first_facility', 'joined-org', ['type' => 'site', 'name' => 'HQ']);
  }

  #[Test]
  public function missingPreparationNeverFallsBackToCreation(): void
  {
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_organization', null, true, []));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->begin(new OrganizationSetupContext('user', 'session', 'missing'), 'create_organization', null, ['name' => 'Company']);
  }

  #[Test]
  public function sessionWithoutExplicitCreationIntentCannotPrepare(): void
  {
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'completed', null, 'joined-org', false, []));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'org', 'payload' => ['name' => 'Company']]]);
  }

  #[Test]
  public function partialCompletionKeepsRemainingPreparedInputs(): void
  {
    $a = new OrganizationSetupOperation('create_first_facility', 'a', ['name' => 'A']);
    $b = new OrganizationSetupOperation('create_first_facility', 'b', ['name' => 'B']);
    $repository = $this->createMock(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_first_facility', 'org', true, [$a, $b]));
    $repository->expects(self::once())->method('saveOperations')->with('session', self::callback(static fn (array $operations): bool => $operations[0] instanceof OrganizationSetupOperation && $operations[1] instanceof OrganizationSetupOperation && 'resource-a' === $operations[0]->resourceId && null === $operations[1]->resourceId && ['name' => 'B'] === $operations[1]->payload));
    $this->service($repository)->complete(new OrganizationSetupContext('user', 'session', 'a'), 'create_first_facility', 'resource-a');
  }

  #[Test]
  public function aSecondOrganizationItemIsRejected(): void
  {
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_organization', null, true, []));
    $this->expectException(OrganizationSetupConflict::class);
    $this->service($repository)->prepare('user', 'session', 'create_organization', [['itemKey' => 'a', 'payload' => ['name' => 'A']], ['itemKey' => 'b', 'payload' => ['name' => 'B']]]);
  }

  #[Test]
  public function omittedNullAndEmptyInvitationRolesHaveTheSamePreparedPayload(): void
  {
    $operation = new OrganizationSetupOperation('invite_members', 'invite', ['email' => 'member@example.com', 'roleIds' => null], 'invitation-id');
    $repository = $this->createStub(OrganizationSetupRepositoryPort::class);
    $repository->method('findSession')->willReturn(new OrganizationSetupSession('session', 'user', 'in_progress', 'create_first_facility', 'org', true, [$operation]));
    $service = $this->service($repository);
    $context = new OrganizationSetupContext('user', 'session', 'invite');
    self::assertSame('invitation-id', $service->begin($context, 'invite_members', 'org', ['email' => ' Member@Example.com '])->resourceId);
    self::assertSame('invitation-id', $service->begin($context, 'invite_members', 'org', ['email' => 'member@example.com', 'roleIds' => []])->resourceId);
  }

  private function service(OrganizationSetupRepositoryPort $repository): OrganizationSetupService
  {
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());

    return new OrganizationSetupService($repository, $transactions);
  }
}
