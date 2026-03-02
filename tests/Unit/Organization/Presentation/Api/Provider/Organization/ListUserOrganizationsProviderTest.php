<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsResult;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Provider\Organization\ListUserOrganizationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(ListUserOrganizationsProvider::class)]
final class ListUserOrganizationsProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListUserOrganizationsProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsUserOrganizations(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441500'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListUserOrganizationsResult([
        new GetOrganizationResult(
          id: '550e8400-e29b-41d4-a716-446655441510',
          name: 'Fireguard Brest',
          slug: 'fireguard-brest',
          ownerUserId: '550e8400-e29b-41d4-a716-446655441500',
          createdByUserId: '550e8400-e29b-41d4-a716-446655441500',
          status: 'active',
          isActive: true,
          createdAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
          updatedAt: new DateTimeImmutable('2026-01-10T12:00:00+00:00'),
          memberCount: 7,
        ),
      ]));

    $provider = new ListUserOrganizationsProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new GetCollection());

    self::assertCount(1, $output);
    self::assertInstanceOf(OrganizationOutput::class, $output[0]);
    self::assertSame('550e8400-e29b-41d4-a716-446655441510', $output[0]->id);
    self::assertSame('Fireguard Brest', $output[0]->name);
    self::assertSame('fireguard-brest', $output[0]->slug);
    self::assertSame('550e8400-e29b-41d4-a716-446655441500', $output[0]->ownerUserId);
    self::assertSame('active', $output[0]->status);
    self::assertTrue($output[0]->isActive);
    self::assertSame(7, $output[0]->memberCount);
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
