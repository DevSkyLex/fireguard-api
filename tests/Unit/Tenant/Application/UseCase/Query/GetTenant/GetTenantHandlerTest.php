<?php

declare(strict_types=1);

namespace Tests\Tenant\Application\UseCase\Query\GetTenant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Application\UseCase\Query\GetTenant\GetTenantHandler;
use Tenant\Application\UseCase\Query\GetTenant\GetTenantQuery;
use Tenant\Application\UseCase\Query\GetTenant\GetTenantResult;
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Domain\ValueObject\TenantName;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Test GetTenantHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GetTenantHandler::class)]
final class GetTenantHandlerTest extends TestCase
{
    // #region Methods
    /**
     * Method testInvokeReturnsTenant.
     *
     * Test that __invoke returns tenant details successfully.
     */
    #[Test]
    public function testInvokeReturnsTenant(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';

        $tenant = Tenant::create(
            id: new TenantId($tenantId),
            name: new TenantName('Test Tenant'),
            settings: new TenantSettings(accessTokenTtl: 7200),
        );

        $repository = $this->createMock(TenantRepositoryPort::class);
        $repository->expects(self::once())
          ->method('findById')
          ->willReturn($tenant);

        $query = new GetTenantQuery(tenantId: $tenantId);

        $handler = new GetTenantHandler(tenantRepository: $repository);
        $result = $handler->__invoke(query: $query);

        self::assertInstanceOf(GetTenantResult::class, $result);
        self::assertEquals($tenantId, $result->tenantId);
        self::assertEquals('Test Tenant', $result->name);
        self::assertEquals(7200, $result->settings->accessTokenTtl);
        self::assertTrue($result->isActive);
    }

    /**
     * Method testInvokeThrowsExceptionWhenNotFound.
     *
     * Test that __invoke throws exception when tenant not found.
     */
    #[Test]
    public function testInvokeThrowsExceptionWhenNotFound(): void
    {
        $tenantId = '123e4567-e89b-12d3-a456-426614174000';

        $repository = $this->createMock(TenantRepositoryPort::class);
        $repository->expects(self::once())
          ->method('findById')
          ->willReturn(null);

        $query = new GetTenantQuery(tenantId: $tenantId);

        $handler = new GetTenantHandler(tenantRepository: $repository);

        $this->expectException(TenantNotFoundException::class);
        $handler->__invoke(query: $query);
    }
    // #endregion
}
