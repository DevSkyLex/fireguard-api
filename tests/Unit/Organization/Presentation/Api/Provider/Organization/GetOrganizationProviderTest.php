<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

#[CoversClass(GetOrganizationProvider::class)]
final class GetOrganizationProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetOrganizationProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideMapsAMissingOrganizationToNotFound(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441800'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441810'),
    );

    $provider = new GetOrganizationProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655441810']);
  }

  #[Test]
  public function testProvideReturnsNullWhenIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $output = $provider->provide(new Get(), []);

    self::assertNull($output);
  }

  #[Test]
  public function testProvideThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441600', '550e8400-e29b-41d4-a716-446655441610', 'organization.read')
      ->willReturn(false);

    $provider = new GetOrganizationProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsQueryResultToOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationQuery::class))
      ->willReturn(new GetOrganizationResult(
        id: '550e8400-e29b-41d4-a716-446655441610',
        name: 'Fireguard Dijon',
        slug: 'fireguard-dijon',
        ownerUserId: '550e8400-e29b-41d4-a716-446655441600',
        createdByUserId: '550e8400-e29b-41d4-a716-446655441600',
        status: 'active',
        isActive: true,
        createdAt: new DateTimeImmutable('2026-02-01T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-01T08:00:00+00:00'),
        memberCount: 3,
        country: 'FR',
        legalType: 'limited_liability_company',
        legalName: 'Fireguard Dijon SARL',
        registrationNumber: 'RCS DIJON 812345678',
        vatNumber: 'FR12345678901',
      ));

    $provider = new GetOrganizationProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(OrganizationOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441610', $output->id);
    self::assertSame('Fireguard Dijon', $output->name);
    self::assertSame('fireguard-dijon', $output->slug);
    self::assertSame('550e8400-e29b-41d4-a716-446655441600', $output->ownerUserId);
    self::assertSame('active', $output->status);
    self::assertTrue($output->isActive);
    self::assertSame(3, $output->memberCount);
    self::assertSame('FR', $output->country);
    self::assertSame('limited_liability_company', $output->legalType);
    self::assertSame('Fireguard Dijon SARL', $output->legalName);
    self::assertSame('RCS DIJON 812345678', $output->registrationNumber);
    self::assertSame('FR12345678901', $output->vatNumber);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
