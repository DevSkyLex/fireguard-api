<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Command\Tenant\ActivateTenant;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\Tenant\ActivateTenant\{ActivateTenantCommand, ActivateTenantHandler};
use Tenant\Domain\Event\TenantActivatedEvent;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Test ActivateTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ActivateTenantHandler::class)]
final class ActivateTenantHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeActivatesTenantAndDispatchesEvent(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174002';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Tenant'),
      settings: new TenantSettings(),
    );
    $tenant->deactivate();

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
      ->willReturn(new Uuid('00000000-0000-4000-a000-000000000002'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TenantActivatedEvent::class));

    $handler = new ActivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new ActivateTenantCommand(tenantId: $tenantId));

    self::assertTrue($tenant->isActive());
  }

  #[Test]
  public function testInvokeDoesNotDispatchWhenAlreadyActive(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174003';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Tenant'),
      settings: new TenantSettings(),
    );

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

    $handler = new ActivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new ActivateTenantCommand(tenantId: $tenantId));

    self::assertTrue($tenant->isActive());
  }

  #[Test]
  public function testInvokeThrowsWhenTenantNotFound(): void
  {
    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new ActivateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $this->createMock(UuidFactory::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
    );

    $this->expectException(TenantNotFoundException::class);

    $handler->__invoke(new ActivateTenantCommand(tenantId: '123e4567-e89b-12d3-a456-426614174999'));
  }
  // #endregion
}
