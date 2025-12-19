<?php

declare(strict_types=1);

namespace Tests\Tenant\Application\UseCase\Command\CreateTenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantCommand;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantHandler;
use Tenant\Application\UseCase\Command\CreateTenant\CreateTenantResult;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;

/**
 * Test CreateTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: CreateTenantHandler::class)]
final class CreateTenantHandlerTest extends TestCase
{
    // #region Methods
    /**
     * Method testInvokeCreatesNewTenant.
     *
     * Test that __invoke creates a new tenant successfully.
     */
    #[Test]
    public function testInvokeCreatesNewTenant(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';

        // Mocks
        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects(self::once())
          ->method('create')
          ->with(TenantId::class)
          ->willReturn(new TenantId($tenantId));

        $repository = $this->createMock(TenantRepositoryPort::class);
        $repository->expects(self::once())
          ->method('save')
          ->with(self::isInstanceOf(Tenant::class));

        // Command
        $command = new CreateTenantCommand(
            name: 'Test Tenant',
        );

        // Handler
        $handler = new CreateTenantHandler(
            tenantRepository: $repository,
            uuidFactory: $uuidFactory,
        );

        // Execute
        $result = $handler->__invoke(command: $command);

        // Assert
        self::assertInstanceOf(CreateTenantResult::class, $result);
        self::assertEquals($tenantId, $result->tenantId);
    }
    // #endregion
}
