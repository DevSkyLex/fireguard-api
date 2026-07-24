<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Domain\Exception\DeferredActionNoLongerApplicableException;
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\DecommissionEquipmentCommand;
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Infrastructure\Adapter\Approval\EquipmentDecommissionExecutorAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test EquipmentDecommissionExecutorAdapterTest.
 *
 * @category Infrastructure Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentDecommissionExecutorAdapter::class)]
final class EquipmentDecommissionExecutorAdapterTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string EQUIP_ID = 'equip-1';

  #[Test]
  public function testActionTypeIsEquipmentDecommission(): void
  {
    $adapter = new EquipmentDecommissionExecutorAdapter($this->createStub(CommandBusPort::class));

    self::assertSame('equipment_decommission', $adapter->actionType());
  }

  #[Test]
  public function testExecuteDispatchesTheDecommissionCommandUnchanged(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (DecommissionEquipmentCommand $command): bool {
        return self::ORG_ID === $command->organizationId && self::EQUIP_ID === $command->equipmentId;
      }));

    $adapter = new EquipmentDecommissionExecutorAdapter($commandBus);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteIsIdempotentWhenAlreadyDecommissioned(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(EquipmentAlreadyDecommissionedException::withId(self::EQUIP_ID)),
    );

    $adapter = new EquipmentDecommissionExecutorAdapter($commandBus);

    // Must not throw: decommissioned is a single terminal state.
    $adapter->execute(self::context());
    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testExecuteThrowsNoLongerApplicableWhenEquipmentNoLongerExists(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(EquipmentNotFoundException::withId(self::EQUIP_ID)),
    );

    $adapter = new EquipmentDecommissionExecutorAdapter($commandBus);

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    $adapter->execute(self::context());
  }

  #[Test]
  public function testExecuteRethrowsUnrelatedFailures(): void
  {
    $original = MessengerRuntimeException::wrap(new RuntimeException('unexpected'));

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($original);

    $adapter = new EquipmentDecommissionExecutorAdapter($commandBus);

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('unexpected');

    $adapter->execute(self::context());
  }

  private static function context(): DeferredActionContext
  {
    return new DeferredActionContext(
      self::ORG_ID,
      'equipment_decommission',
      self::EQUIP_ID,
      ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }
}
