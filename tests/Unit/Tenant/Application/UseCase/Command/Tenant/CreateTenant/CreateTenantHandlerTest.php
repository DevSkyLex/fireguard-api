<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Command\Tenant\CreateTenant;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\Tenant\CreateTenant\{CreateTenantCommand, CreateTenantHandler, CreateTenantResult};
use Tenant\Domain\Event\TenantCreatedEvent;
use Tenant\Domain\Model\Tenant\Tenant;
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
    $eventId = '00000000-0000-4000-a000-000000000001';

    // Mocks
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::exactly(2))
      ->method('create')
      ->willReturnCallback(static function (string $class) use ($tenantId, $eventId): object {
        return match ($class) {
          TenantId::class => new TenantId($tenantId),
          Uuid::class => new Uuid($eventId),
          default => throw new InvalidArgumentException('Unexpected UUID class.'),
        };
      });

    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Tenant::class));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TenantCreatedEvent::class));

    // Command
    $command = new CreateTenantCommand(
      name: 'Test Tenant',
    );

    // Handler
    $handler = new CreateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    // Execute
    $result = $handler->__invoke(command: $command);

    // Assert
    self::assertInstanceOf(CreateTenantResult::class, $result);
    self::assertEquals($tenantId, $result->tenantId);
  }
  // #endregion
}
