<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Processor\Tenant;

use ApiPlatform\Metadata\Operation;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Tenant\Application\UseCase\Command\Tenant\ActivateTenant\ActivateTenantCommand;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\{GetTenantQuery, GetTenantResult};
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Processor\Tenant\ActivateTenantProcessor;

/**
 * Test ActivateTenantProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ActivateTenantProcessor::class)]
final class ActivateTenantProcessorTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProcessThrowsWhenIdIsNotString(): void
  {
    $processor = new ActivateTenantProcessor(
      commandBus: $this->createMock(CommandBusPort::class),
      queryBus: $this->createMock(QueryBusPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $processor->process(
      data: null,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => 123],
    );
  }

  #[Test]
  public function testProcessDispatchesAndReturnsOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (ActivateTenantCommand $command): bool => 'tenant-123' === $command->tenantId,
      ));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetTenantQuery::class))
      ->willReturn(self::createTenantResult());

    $processor = new ActivateTenantProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus,
    );

    $output = $processor->process(
      data: null,
      operation: $this->createMock(Operation::class),
      uriVariables: ['id' => 'tenant-123'],
    );

    self::assertInstanceOf(TenantOutput::class, $output);
    self::assertSame('tenant-123', $output->id);
    self::assertTrue($output->isActive);
    self::assertSame(3600, $output->accessTokenTtl);
  }

  private static function createTenantResult(): GetTenantResult
  {
    return new GetTenantResult(
      tenantId: 'tenant-123',
      name: 'Tenant',
      settings: new TenantSettings(),
      isActive: true,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
