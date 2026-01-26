<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Presentation\Api\Provider\Tenant;

use ApiPlatform\Metadata\Get;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Security\Core\User\UserInterface;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\{GetTenantQuery, GetTenantResult};
use Tenant\Domain\Exception\TenantNotFoundException;
use Tenant\Domain\ValueObject\TenantSettings;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;
use Tenant\Presentation\Api\Provider\Tenant\GetTenantProvider;

/**
 * Test GetTenantProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetTenantProvider::class)]
final class GetTenantProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new GetTenantProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideThrowsWhenIdMissing(): void
  {
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new GetTenantProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => null]);
  }

  #[Test]
  public function testProvideMapsResult(): void
  {
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $result = new GetTenantResult(
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
      ->with(self::isInstanceOf(GetTenantQuery::class))
      ->willReturn($result);

    $provider = new GetTenantProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['id' => 'tenant-1']);

    self::assertInstanceOf(TenantOutput::class, $output);
    self::assertSame('Acme', $output->name);
    self::assertTrue($output->isActive);
  }

  #[Test]
  public function testProvideMapsNotFound(): void
  {
    $user = $this->createMock(UserInterface::class);
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(TenantNotFoundException::withId('tenant-2'));

    $provider = new GetTenantProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => 'tenant-2']);
  }
  // #endregion
}
