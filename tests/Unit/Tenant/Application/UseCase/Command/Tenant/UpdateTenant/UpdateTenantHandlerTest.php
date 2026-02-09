<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Command\Tenant\UpdateTenant;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Domain\ValueObject\Uuid;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\Tenant\UpdateTenant\{UpdateTenantCommand, UpdateTenantHandler, UpdateTenantResult};
use Tenant\Domain\Event\TenantSettingsUpdatedEvent;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Test UpdateTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UpdateTenantHandler::class)]
final class UpdateTenantHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeUpdatesTenantAndDispatchesEvent(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174000';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Old Name'),
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
      ->willReturn(new Uuid('00000000-0000-4000-a000-000000000001'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(TenantSettingsUpdatedEvent::class));

    $command = new UpdateTenantCommand(
      tenantId: $tenantId,
      name: 'New Name',
      settings: new TenantSettings(accessTokenTtl: 7200),
    );

    $handler = new UpdateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(UpdateTenantResult::class, $result);
    self::assertSame('New Name', (string) $tenant->name());
    self::assertSame(7200, $tenant->settings()->accessTokenTtl);
  }

  #[Test]
  public function testInvokeDoesNotDispatchWhenSettingsNull(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174001';

    $tenant = Tenant::create(
      id: new TenantId($tenantId),
      name: new TenantName('Old Name'),
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

    $command = new UpdateTenantCommand(
      tenantId: $tenantId,
      name: 'Renamed Tenant',
      settings: null,
    );

    $handler = new UpdateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $uuidFactory,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke($command);

    self::assertSame('Renamed Tenant', (string) $tenant->name());
  }

  #[Test]
  public function testInvokeThrowsWhenTenantNotFound(): void
  {
    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new UpdateTenantHandler(
      tenantRepository: $repository,
      uuidFactory: $this->createMock(UuidFactory::class),
      eventDispatcher: $this->createMock(EventDispatcherPort::class),
    );

    $this->expectException(TenantNotFoundException::class);

    $handler->__invoke(new UpdateTenantCommand(tenantId: '123e4567-e89b-12d3-a456-426614174999'));
  }
  // #endregion
}
