<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Command\Tenant\DeactivateTenant;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\Tenant\DeactivateTenant\{DeactivateTenantCommand, DeactivateTenantHandler};
use Tenant\Domain\Event\TenantDeactivatedEvent;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Test DeactivateTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeactivateTenantHandler::class)]
final class DeactivateTenantHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeDeactivatesTenantAndDispatchesEvent(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174004';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Tenant'),
      settings: new TenantSettings(),
    );

    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::equalTo(TenantId::fromString($tenantId)))
      ->willReturn($tenant);
    $repository->expects(self::once())
      ->method('save')
      ->with($tenant);

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(Uuid::class)
      ->willReturn(new Uuid('00000000-0000-4000-a000-000000000003'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TenantDeactivatedEvent::class));

    $handler = new DeactivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new DeactivateTenantCommand(tenantId: $tenantId));

    self::assertFalse($tenant->isActive());
  }

  #[Test]
  public function testInvokeDoesNotDispatchWhenAlreadyInactive(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174005';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Tenant'),
      settings: new TenantSettings(),
    );
    $tenant->deactivate();

    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($tenant);
    $repository->expects(self::once())
      ->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())
      ->method('create');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())
      ->method('dispatch');

    $handler = new DeactivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new DeactivateTenantCommand(tenantId: $tenantId));

    self::assertFalse($tenant->isActive());
  }

  #[Test]
  public function testInvokeThrowsWhenTenantNotFound(): void
  {
    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new DeactivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $this->createStub(UuidFactory::class),
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(TenantNotFoundException::class);

    $handler->__invoke(new DeactivateTenantCommand(tenantId: '123e4567-e89b-12d3-a456-426614174999'));
  }
  // #endregion
}
