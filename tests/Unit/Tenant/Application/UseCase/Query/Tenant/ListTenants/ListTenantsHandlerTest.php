<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Application\UseCase\Query\Tenant\ListTenants;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Query\Tenant\ListTenants\{ListTenantsHandler, ListTenantsQuery};
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};

/**
 * Test ListTenantsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListTenantsHandler::class)]
final class ListTenantsHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeReturnsMappedTenants(): void
  {
    $tenant = Tenant::create(
      id: TenantId::fromString('550e8400-e29b-41d4-a716-446655440000'),
      name: new TenantName('Acme'),
      settings: new TenantSettings(),
    );

    /** @var TenantRepositoryPort&MockObject $repository */
    $repository = $this->createMock(TenantRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findAll')
      ->willReturn([$tenant]);

    $handler = new ListTenantsHandler($repository);

    $result = $handler->__invoke(new ListTenantsQuery());

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame('Acme', $result->items[0]->name);
    self::assertSame(1, $result->total);
    self::assertSame(1, $result->limit);
    self::assertSame(0, $result->offset);
  }
  // #endregion
}
