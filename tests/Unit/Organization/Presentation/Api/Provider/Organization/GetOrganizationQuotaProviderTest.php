<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationQuota\{
  GetOrganizationQuotaQuery,
  GetOrganizationQuotaResult
};
use Organization\Presentation\Api\Provider\Organization\GetOrganizationQuotaProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test GetOrganizationQuotaProvider.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationQuotaProvider::class)]
final class GetOrganizationQuotaProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441800';

  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetOrganizationQuotaProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideReturnsNullWhenTheOrganizationIdIsMissing(): void
  {
    $provider = new GetOrganizationQuotaProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->securityFor(self::USER_ID),
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function testProvideThrowsWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new GetOrganizationQuotaProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideMapsQuotaItemsOntoTheOutput(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      function (object $query) use (&$asked): GetOrganizationQuotaResult {
        $asked = $query;

        return new GetOrganizationQuotaResult([
          ['resource' => 'members', 'used' => 4, 'limit' => 10],
          ['resource' => 'facilities', 'used' => 2, 'limit' => null],
        ]);
      },
    );

    $provider = new GetOrganizationQuotaProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->securityFor(self::USER_ID),
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(GetOrganizationQuotaQuery::class, $asked);
    self::assertNotNull($output);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertCount(2, $output->items);
    self::assertSame('members', $output->items[0]->resource);
    self::assertSame(4, $output->items[0]->used);
    self::assertSame(10, $output->items[0]->limit);
    self::assertNull($output->items[1]->limit);
  }

  /**
   * Builds a security stub returning an authenticated user.
   */
  private function securityFor(string $userId): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: $userId,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
