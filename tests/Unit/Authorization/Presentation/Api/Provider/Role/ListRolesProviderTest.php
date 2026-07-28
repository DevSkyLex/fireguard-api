<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Provider\Role;

use ApiPlatform\Metadata\GetCollection;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Role\GetRole\GetRoleResult;
use Authorization\Application\UseCase\Query\Role\ListRoles\{ListRolesQuery, ListRolesResult};
use Authorization\Presentation\Api\Provider\Role\ListRolesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test ListRolesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListRolesProvider::class)]
final class ListRolesProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsRoles(): void
  {
    $roleResult = new GetRoleResult(
      id: '550e8400-e29b-41d4-a716-446655440010',
      name: 'editor',
      description: 'Editor role',
      isSystem: false,
      createdAt: '2025-01-02 12:00:00',
      permissions: [
        new GetPermissionResult(
          id: '660e8400-e29b-41d4-a716-446655440010',
          name: 'posts.edit',
          description: 'Edit posts',
          createdAt: '2025-01-02 12:00:00',
        ),
      ],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListRolesResult(roles: [$roleResult]));

    $provider = new ListRolesProvider($queryBus);

    $result = $provider->provide(new GetCollection());

    self::assertCount(1, $result);
    self::assertSame('editor', $result[0]->name);
    self::assertCount(1, $result[0]->permissions);
  }

  #[Test]
  public function testProvidePassesIsSystemFilterToQuery(): void
  {
    $systemRoleResult = new GetRoleResult(
      id: '550e8400-e29b-41d4-a716-446655440020',
      name: 'admin',
      description: 'Admin system role',
      isSystem: true,
      createdAt: '2025-01-01 00:00:00',
      permissions: [],
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        fn (ListRolesQuery $query) => true === $query->isSystem,
      ))
      ->willReturn(new ListRolesResult(roles: [$systemRoleResult]));

    $provider = new ListRolesProvider($queryBus);

    $result = $provider->provide(
      operation: new GetCollection(),
      context: ['filters' => ['isSystem' => 'true']],
    );

    self::assertCount(1, $result);
    self::assertTrue($result[0]->isSystem);
  }

  #[Test]
  public function testProvideWithNoIsSystemFilterPassesNull(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        fn (ListRolesQuery $query) => null === $query->isSystem,
      ))
      ->willReturn(new ListRolesResult(roles: []));

    $provider = new ListRolesProvider($queryBus);

    $result = $provider->provide(new GetCollection());

    self::assertCount(0, $result);
  }

  #[Test]
  public function testProvideAcceptsNativeBooleanIsSystemFilter(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        fn (ListRolesQuery $query) => false === $query->isSystem,
      ))
      ->willReturn(new ListRolesResult(roles: []));

    $provider = new ListRolesProvider($queryBus);

    $result = $provider->provide(
      operation: new GetCollection(),
      context: ['filters' => ['isSystem' => false]],
    );

    self::assertCount(0, $result);
  }

  #[Test]
  public function testProvideThrowsBadRequestWhenIsSystemFilterIsInvalid(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())
      ->method('ask');

    $provider = new ListRolesProvider($queryBus);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The isSystem filter must be a valid boolean.');

    $provider->provide(
      operation: new GetCollection(),
      context: ['filters' => ['isSystem' => 'foo']],
    );
  }
  // #endregion
}
