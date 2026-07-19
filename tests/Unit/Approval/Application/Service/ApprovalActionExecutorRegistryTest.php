<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Service;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Application\Service\ApprovalActionExecutorRegistry;
use Approval\Domain\Exception\ApprovalActionExecutorNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalActionExecutorRegistryTest.
 *
 * @category Application Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalActionExecutorRegistry::class)]
final class ApprovalActionExecutorRegistryTest extends TestCase
{
  #[Test]
  public function testExecuteRoutesToTheMatchingExecutor(): void
  {
    $context = new DeferredActionContext('org-1', 'equipment_decommission', 'equip-1', []);

    $ncExecutor = $this->createStub(ApprovalActionExecutorPort::class);
    $ncExecutor->method('actionType')->willReturn('nc_waiver');

    /** @var ApprovalActionExecutorPort&MockObject $equipmentExecutor */
    $equipmentExecutor = $this->createMock(ApprovalActionExecutorPort::class);
    $equipmentExecutor->method('actionType')->willReturn('equipment_decommission');
    $equipmentExecutor->expects(self::once())->method('execute')->with($context);

    $registry = new ApprovalActionExecutorRegistry([$ncExecutor, $equipmentExecutor]);

    $registry->execute($context);
  }

  #[Test]
  public function testExecuteThrowsWhenNoExecutorSupportsTheActionType(): void
  {
    $ncExecutor = $this->createStub(ApprovalActionExecutorPort::class);
    $ncExecutor->method('actionType')->willReturn('nc_waiver');

    $registry = new ApprovalActionExecutorRegistry([$ncExecutor]);

    $this->expectException(ApprovalActionExecutorNotFoundException::class);

    $registry->execute(new DeferredActionContext('org-1', 'unknown_action', 'subject-1', []));
  }
}
