<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Tenant\Application\UseCase\Command\Tenant\DeleteTenant\DeleteTenantCommand;
use Tenant\Presentation\Api\Processor\Tenant\DeleteTenantProcessor;

/**
 * Test DeleteTenantProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteTenantProcessor::class)]
final class DeleteTenantProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new DeleteTenantProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: null,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => []],
    );
  }

  #[Test]
  public function testProcessDispatchesCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (DeleteTenantCommand $command): bool => 'tenant-123' === $command->tenantId,
      ));

    $processor = new DeleteTenantProcessor(commandBus: $commandBus);

    $processor->process(
      data: null,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => 'tenant-123'],
    );
  }
  // #endregion
}
