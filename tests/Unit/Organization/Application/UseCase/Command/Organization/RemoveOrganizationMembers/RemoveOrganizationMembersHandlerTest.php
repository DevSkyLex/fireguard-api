<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\Internal\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Outbound\Internal\OrganizationMemberRepositoryPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\{RemoveOrganizationMemberCommand, RemoveOrganizationMemberResult};
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers\{RemoveOrganizationMembersCommand, RemoveOrganizationMembersHandler, RemoveOrganizationMembersResult};
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use Organization\Infrastructure\Adapter\Idempotency\OrganizationHttpIdempotencyOperationContributionAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencySnapshot};
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Infrastructure\Idempotency\{HmacIdempotencyFingerprintAdapter, HttpIdempotencyExecutionContext, HttpIdempotencyResultCodec};
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function base64_encode;
use function str_repeat;

/**
 * Class RemoveOrganizationMembersHandlerTest
 *
 * Preserves partial target failures while making a keyed batch one durable outcome.
 *
 * @category Handler Tests
 */
#[CoversClass(RemoveOrganizationMembersHandler::class)]
final class RemoveOrganizationMembersHandlerTest extends TestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-446655479001';

  private const string FIRST = '550e8400-e29b-41d4-a716-446655479002';

  private const string MISSING = '550e8400-e29b-41d4-a716-446655479003';

  private const string FOREIGN = '550e8400-e29b-41d4-a716-446655479004';

  #[Test]
  public function missingAndForeignTargetsFailWithoutEnteringTheirMutation(): void
  {
    $ids = [self::FIRST, self::MISSING, self::FOREIGN];
    $guard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $guard->expects(self::once())->method('assertCanRemoveMember')->with(self::ORG, self::FIRST);
    $guard->expects(self::once())->method('assertCanRemoveMembers')->with(self::ORG, $ids);
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::exactly(3))->method('findById')->willReturnOnConsecutiveCalls($this->member(self::FIRST, self::ORG), null, $this->member(self::FOREIGN, '550e8400-e29b-41d4-a716-446655479099'));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $command): bool => $command instanceof RemoveOrganizationMemberCommand && self::FIRST === $command->memberId && self::ORG === $command->organizationId))->willReturn(new RemoveOrganizationMemberResult(self::FIRST, self::ORG));

    $result = new RemoveOrganizationMembersHandler($guard, $members, $commands, $this->transaction())->__invoke(new RemoveOrganizationMembersCommand(self::ORG, $ids));

    self::assertSame([self::FIRST], $result->removedIds);
    self::assertSame([self::MISSING, self::FOREIGN], $result->failedIds);
  }

  #[Test]
  public function lastAdministratorRefusalPrecedesEveryRemoval(): void
  {
    $guard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $guard->expects(self::once())->method('assertCanRemoveMember');
    $guard->expects(self::once())->method('assertCanRemoveMembers')->willThrowException(OrganizationLastAdminException::cannotRemoveLastAdmin());
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::never())->method('findById');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $this->expectException(OrganizationLastAdminException::class);
    new RemoveOrganizationMembersHandler($guard, $members, $commands, $this->transaction())->__invoke(new RemoveOrganizationMembersCommand(self::ORG, [self::FIRST]));
  }

  #[Test]
  public function unexpectedMutationFailurePropagatesToTheOuterRollback(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($this->member(self::FIRST, self::ORG));
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('Persistence failed.'));
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Persistence failed.');
    new RemoveOrganizationMembersHandler($this->createStub(OrganizationLastAdminGuardPort::class), $members, $commands, $this->transaction())->__invoke(new RemoveOrganizationMembersCommand(self::ORG, [self::FIRST]));
  }

  #[Test]
  public function completedReplayPreservesFailedIdsWithoutRemovingALaterExistingMember(): void
  {
    $operation = null;
    foreach (OrganizationHttpIdempotencyOperationContributionAdapter::definitions() as $candidate) {
      if (RemoveOrganizationMembersCommand::class === $candidate->primaryCommand) {
        $operation = $candidate;
      }
    }
    self::assertNotNull($operation);
    $codec = new HttpIdempotencyResultCodec();
    $receipt = new IdempotencySnapshot(200, $codec->encode(new RemoveOrganizationMembersResult([self::FIRST], [self::MISSING]), $operation));
    $requests = new RequestStack();
    $requests->push(new Request());
    $context = new HttpIdempotencyExecutionContext($requests, $codec, new HmacIdempotencyFingerprintAdapter(base64_encode(str_repeat('x', 32))), $this->createStub(Connection::class), $this->createStub(Connection::class));
    $engine = $this->createMock(HttpIdempotencyPort::class);
    $engine->expects(self::never())->method('claimWithinTransaction');
    $engine->expects(self::never())->method('beforeCommit');
    $context->activate($operation, $engine, new IdempotencyBegin(replay: $receipt), []);
    $result = $context->dispatchPrimary(new RemoveOrganizationMembersCommand(self::ORG, [self::FIRST, self::MISSING]), static function (): never {
      self::fail('A later-existing target must not be removed by replaying its old failed batch.');
    });

    self::assertInstanceOf(RemoveOrganizationMembersResult::class, $result);
    self::assertSame([self::FIRST], $result->removedIds);
    self::assertSame([self::MISSING], $result->failedIds);
  }

  private function member(string $id, string $organizationId): OrganizationMember
  {
    return OrganizationMember::reconstitute(new OrganizationMemberId($id), new OrganizationId($organizationId), '550e8400-e29b-41d4-a716-446655479098', true, new DateTimeImmutable('2026-01-01'));
  }

  private function transaction(): TransactionManagerPort
  {
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactions;
  }
}
