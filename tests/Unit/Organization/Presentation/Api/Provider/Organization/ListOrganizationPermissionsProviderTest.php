<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationPermissions\{
  GetOrganizationPermissionResult,
  ListOrganizationPermissionsQuery,
  ListOrganizationPermissionsResult
};
use Organization\Presentation\Api\Provider\Organization\ListOrganizationPermissionsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test ListOrganizationPermissionsProvider.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationPermissionsProvider::class)]
final class ListOrganizationPermissionsProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441800';

  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListOrganizationPermissionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideReturnsAnEmptyListWhenTheOrganizationIdIsMissing(): void
  {
    $provider = new ListOrganizationPermissionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->securityFor(self::USER_ID),
    );

    self::assertSame([], $provider->provide(new GetCollection(), []));
  }

  #[Test]
  public function testProvideThrowsWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new ListOrganizationPermissionsProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $this->securityFor(self::USER_ID),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProvideMapsPermissionsAndSortsThemByNameByDefault(): void
  {
    $provider = $this->provider($asked);

    $outputs = $provider->provide(new GetCollection(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(ListOrganizationPermissionsQuery::class, $asked);
    self::assertCount(3, $outputs);
    self::assertSame('organization.members.read', $outputs[0]->name);
    self::assertSame('organization.read', $outputs[1]->name);
    self::assertSame('organization.roles.read', $outputs[2]->name);
    self::assertSame('Read members', $outputs[0]->description);
  }

  #[Test]
  public function testProvideNarrowsTheCollectionWithTheSearchFilter(): void
  {
    $provider = $this->provider($asked);

    $outputs = $provider->provide(
      new GetCollection(),
      ['organizationId' => self::ORGANIZATION_ID],
      ['filters' => ['search' => 'roles']],
    );

    self::assertCount(1, $outputs);
    self::assertSame('organization.roles.read', $outputs[0]->name);
  }

  /**
   * Builds a fully authorized provider over a fixed permission set.
   */
  private function provider(?object &$asked): ListOrganizationPermissionsProvider
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query) use (&$asked): ListOrganizationPermissionsResult {
        $asked = $query;

        return new ListOrganizationPermissionsResult([
          new GetOrganizationPermissionResult('organization.read', 'Read organization'),
          new GetOrganizationPermissionResult('organization.roles.read', 'Read roles'),
          new GetOrganizationPermissionResult('organization.members.read', 'Read members'),
        ]);
      },
    );

    return new ListOrganizationPermissionsProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->securityFor(self::USER_ID),
    );
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
