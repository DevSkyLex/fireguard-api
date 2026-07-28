<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Domain\Exception\DeferredActionNoLongerApplicableException;
use DateTimeImmutable;
use Inspection\Application\UseCase\Command\NonConformity\UpdateNonConformityStatus\UpdateNonConformityStatusCommand;
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\GetNonConformityResult;
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityAlreadyResolvedException, NonConformityNotFoundException};
use Inspection\Infrastructure\Adapter\Approval\NonConformityWaiverExecutorAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};

/**
 * Test NonConformityWaiverExecutorAdapterTest.
 *
 * @category Infrastructure Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityWaiverExecutorAdapter::class)]
final class NonConformityWaiverExecutorAdapterTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string INSP_ID = 'insp-1';

  private const string NC_ID = 'nc-1';

  #[Test]
  public function testActionTypeIsNcWaiver(): void
  {
    $adapter = new NonConformityWaiverExecutorAdapter(
      $this->createStub(CommandBusPort::class),
      $this->createStub(QueryBusPort::class),
    );

    self::assertSame('nc_waiver', $adapter->actionType());
  }

  #[Test]
  public function testExecuteDispatchesTheWaiverCommandUnchanged(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateNonConformityStatusCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::INSP_ID === $command->inspectionId
          && self::NC_ID === $command->nonConformityId
          && 'waived' === $command->status;
      }));

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $this->createStub(QueryBusPort::class));

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteIsIdempotentWhenAlreadyWaived(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(NonConformityAlreadyResolvedException::withId(self::NC_ID)),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(self::ncResult('waived'));

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $queryBus);

    // Must not throw: the non-conformity is already in the target state.
    $adapter->execute(self::context());
    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testExecuteThrowsNoLongerApplicableWhenResolvedWithADifferentOutcome(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(NonConformityAlreadyResolvedException::withId(self::NC_ID)),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(self::ncResult('done'));

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $queryBus);

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteThrowsNoLongerApplicableWhenNonConformityNoLongerExists(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(NonConformityNotFoundException::withId(self::NC_ID)),
    );

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $this->createStub(QueryBusPort::class));

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteThrowsNoLongerApplicableWhenInspectionIdMissingFromPayload(): void
  {
    $adapter = new NonConformityWaiverExecutorAdapter($this->createStub(CommandBusPort::class), $this->createStub(QueryBusPort::class));

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(new DeferredActionContext(self::ORG_ID, 'nc_waiver', self::NC_ID, []));
  }

  #[Test]
  public function testExecuteThrowsNoLongerApplicableWhenTheInspectionNoLongerExists(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(InspectionNotFoundException::withId(self::INSP_ID)),
    );

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $this->createStub(QueryBusPort::class));

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteRethrowsAnUnrelatedDispatchFailure(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Connection lost.')),
    );

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $this->createStub(QueryBusPort::class));

    $this->expectException(MessengerRuntimeException::class);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteTreatsAnUnreadableNonConformityAsNotAlreadyWaived(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(NonConformityAlreadyResolvedException::withId(self::NC_ID)),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('Read model unavailable.'));

    $adapter = new NonConformityWaiverExecutorAdapter($commandBus, $queryBus);

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(self::context());
  }

  private static function context(): DeferredActionContext
  {
    return new DeferredActionContext(
      self::ORG_ID,
      'nc_waiver',
      self::NC_ID,
      ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID, 'nonConformityId' => self::NC_ID, 'severity' => 'critical'],
    );
  }

  private static function ncResult(string $status): GetNonConformityResult
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new GetNonConformityResult(
      nonConformityId: self::NC_ID,
      inspectionId: self::INSP_ID,
      description: 'desc',
      severity: 'critical',
      status: $status,
      dueAt: null,
      resolvedAt: null,
      notes: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }
}
