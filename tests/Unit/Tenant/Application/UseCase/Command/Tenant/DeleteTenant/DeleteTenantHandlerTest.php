<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Command\Tenant\DeleteTenant;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Command\Tenant\DeleteTenant\{DeleteTenantCommand, DeleteTenantHandler, DeleteTenantResult};
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Test DeleteTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteTenantHandler::class)]
final class DeleteTenantHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeDeletesTenant(): void
  {
    $tenantId = '123e4567-e89b-12d3-a456-426614174006';

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
      ->method('delete')
      ->with(self::equalTo(TenantId::fromString($tenantId)));

    $handler = new DeleteTenantHandler(tenantRepository: $repository);

    $result = $handler->__invoke(new DeleteTenantCommand(tenantId: $tenantId));

    self::assertInstanceOf(DeleteTenantResult::class, $result);
    self::assertSame($tenantId, $result->tenantId);
  }

  #[Test]
  public function testInvokeThrowsWhenTenantNotFound(): void
  {
    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new DeleteTenantHandler(tenantRepository: $repository);

    $this->expectException(TenantNotFoundException::class);

    $handler->__invoke(new DeleteTenantCommand(tenantId: '123e4567-e89b-12d3-a456-426614174999'));
  }
  // #endregion
}
