<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment;

use DateTimeImmutable;
use Equipment\Application\Contract\Replacement\{EquipmentReplacementReceipt, EquipmentReplacementSuccessor};
use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\{EquipmentReplacementRepositoryPort, FacilityValidationPort};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentResult};
use Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment\{ReplaceEquipmentCommand, ReplaceEquipmentHandler};
use Equipment\Domain\Exception\{EquipmentNotFoundException, EquipmentReplacementConflictException};
use Equipment\Domain\Model\Replacement\{EquipmentReplacement, ReplacementEquipment};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;

/**
 * Class ReplaceEquipmentHandlerTest
 *
 * Checks journal replay, new asset creation and refusal paths before any replacement effects.
 *
 * @category Unit Tests
 */
final class ReplaceEquipmentHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method executesAndReplaysWithoutAnotherLifecycleWrite
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function executesAndReplaysWithoutAnotherLifecycleWrite(): void
  {
    $receipt = null;
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->method('findReceiptForUpdate')->willReturnCallback(static function () use (&$receipt): ?EquipmentReplacementReceipt {
      return $receipt;
    });
    $repository->expects(self::once())->method('lockEquipment')->with('org', ['old', 'new'])->willReturn($this->candidates());
    $repository->expects(self::once())->method('save')->willReturnCallback(static function (EquipmentReplacement $replacement, EquipmentReplacementReceipt $saved) use (&$receipt): void {
      self::assertSame('under_maintenance', $replacement->predecessor->status);
      self::assertSame('operational', $replacement->successorStatus());
      $receipt = $saved;
    });
    $maintenance = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $maintenance->expects(self::once())->method('syncForStatusTransition')->with('old', 'org', 'under_maintenance', 'decommissioned');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $handler = $this->handler($repository, $maintenance, $commands);
    $command = new ReplaceEquipmentCommand('org', 'old', 'operation', 'new');
    $first = $handler($command);
    $replay = $handler($command);
    self::assertFalse($first->replayed);
    self::assertTrue($replay->replayed);
    self::assertSame('old', $replay->predecessorEquipmentId);
    self::assertSame('new', $replay->successorEquipmentId);
    self::assertSame('operation', $replay->clientOperationId);
  }

  /**
   * Method aReusedOperationWithAnotherPayloadConflictsBeforeAnyReadsOrWrites
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function aReusedOperationWithAnotherPayloadConflictsBeforeAnyReadsOrWrites(): void
  {
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->method('findReceiptForUpdate')->willReturn(new EquipmentReplacementReceipt('org', 'operation', 'old', 'new', 'different'));
    $repository->expects(self::never())->method('lockEquipment');
    $repository->expects(self::never())->method('save');
    $this->expectException(EquipmentReplacementConflictException::class);
    $this->handler($repository)(new ReplaceEquipmentCommand('org', 'old', 'operation', 'changed'));
  }

  /**
   * Method createsNewSuccessorBeforeCommittingItsReceipt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function createsNewSuccessorBeforeCommittingItsReceipt(): void
  {
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->method('findReceiptForUpdate')->willReturn(null);
    $repository->expects(self::exactly(2))->method('lockEquipment')->willReturnCallback(function (string $organization, array $ids): array {
      self::assertSame('org', $organization);
      $candidates = $this->candidates();

      return ['old'] === $ids ? ['old' => $candidates['old']] : ['new' => $candidates['new']];
    });
    $repository->expects(self::once())->method('save');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $command): bool => $command instanceof CreateEquipmentCommand && 'org' === $command->organizationId
      && 'site' === $command->facilityId && 'fire_extinguisher' === $command->type
      && 'Reserve asset' === $command->name && 'NEW-001' === $command->assetCode
      && null === $command->interventionId && null === $command->clientId,
    ))->willReturn($this->created());
    $result = $this->handler($repository, commands: $commands)(new ReplaceEquipmentCommand(
      'org',
      'old',
      'operation',
      successor: new EquipmentReplacementSuccessor('fire_extinguisher', name: 'Reserve asset', assetCode: 'NEW-001'),
    ));
    self::assertSame('new', $result->successorEquipmentId);
    self::assertFalse($result->replayed);
  }

  /**
   * Method missingOrForeignSuccessorCannotRetireTheOriginal
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function missingOrForeignSuccessorCannotRetireTheOriginal(): void
  {
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->method('findReceiptForUpdate')->willReturn(null);
    $repository->method('lockEquipment')->willReturn(['old' => $this->candidates()['old']]);
    $repository->expects(self::never())->method('save');
    $this->expectException(EquipmentNotFoundException::class);
    $this->handler($repository)(new ReplaceEquipmentCommand('org', 'old', 'operation', 'foreign'));
  }

  /**
   * Method quotaOrCreationFailureCannotRetireThePredecessor
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function quotaOrCreationFailureCannotRetireThePredecessor(): void
  {
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->method('findReceiptForUpdate')->willReturn(null);
    $repository->method('lockEquipment')->willReturn(['old' => $this->candidates()['old']]);
    $repository->expects(self::never())->method('save');
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->willThrowException(new RuntimeException('quota denied'));
    $maintenance = $this->createMock(EquipmentMaintenanceLogSynchronizerPort::class);
    $maintenance->expects(self::never())->method('syncForStatusTransition');
    $this->expectExceptionMessage('quota denied');
    $this->handler($repository, $maintenance, $commands)(new ReplaceEquipmentCommand(
      'org',
      'old',
      'operation',
      successor: new EquipmentReplacementSuccessor('fire_extinguisher'),
    ));
  }

  /**
   * Method refusesBothOrNeitherSuccessorDescriptions
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function refusesBothOrNeitherSuccessorDescriptions(): void
  {
    $repository = $this->createMock(EquipmentReplacementRepositoryPort::class);
    $repository->expects(self::never())->method('findReceiptForUpdate');
    $this->expectException(EquipmentReplacementConflictException::class);
    $this->handler($repository)(new ReplaceEquipmentCommand('org', 'old', 'operation'));
  }

  /**
   * Method candidates
   *
   * @access private
   *
   * @return array<string, ReplacementEquipment> the domain fixture
   */
  private function candidates(): array
  {
    return [
      'old' => new ReplacementEquipment('old', 'org', 'published', 'under_maintenance', 'site', 'Room'),
      'new' => new ReplacementEquipment('new', 'org', 'published', 'in_stock', null, null),
    ];
  }

  /**
   * Method created
   *
   * @access private
   *
   * @return CreateEquipmentResult the creation bus fixture
   */
  private function created(): CreateEquipmentResult
  {
    return new CreateEquipmentResult('new', 'org', 'site', 'fire_extinguisher', null, null, null, null, null, 'in_stock', null, null, [], new DateTimeImmutable(), new DateTimeImmutable());
  }

  /**
   * Method handler
   *
   * @access private
   *
   * @param EquipmentReplacementRepositoryPort $repository the test repository
   * @param ?EquipmentMaintenanceLogSynchronizerPort $maintenance the lifecycle spy
   * @param ?CommandBusPort $commands the creation bus spy
   *
   * @return ReplaceEquipmentHandler the use case under test
   */
  private function handler(EquipmentReplacementRepositoryPort $repository, ?EquipmentMaintenanceLogSynchronizerPort $maintenance = null, ?CommandBusPort $commands = null): ReplaceEquipmentHandler
  {
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return new ReplaceEquipmentHandler(
      $repository,
      $transactions,
      $maintenance ?? $this->createStub(EquipmentMaintenanceLogSynchronizerPort::class),
      $commands ?? $this->createStub(CommandBusPort::class),
      $this->createStub(FacilityValidationPort::class),
    );
  }
  // #endregion
}
