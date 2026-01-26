<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Provider\Tenant;

use ApiPlatform\Metadata\GetCollection;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult;
use Tenant\Application\UseCase\Query\Tenant\ListTenants\ListTenantsResult;
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Provider\Tenant\ListTenantsProvider;

/**
 * Test ListTenantsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListTenantsProvider::class)]
final class ListTenantsProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListTenantsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsTenants(): void
  {
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $tenantResult = new GetTenantResult(
      tenantId: 'tenant-1',
      name: 'Acme',
      settings: new TenantSettings(),
      isActive: true,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListTenantsResult(
        tenants: [$tenantResult],
        totalCount: 1,
      ));

    $provider = new ListTenantsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(1, $output);
    self::assertSame('Acme', $output[0]->name);
  }
  // #endregion
}
