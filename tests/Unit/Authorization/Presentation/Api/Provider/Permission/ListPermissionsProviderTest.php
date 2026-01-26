<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Provider\Permission;

use ApiPlatform\Metadata\GetCollection;
use Authorization\Application\UseCase\Query\Permission\GetPermission\GetPermissionResult;
use Authorization\Application\UseCase\Query\Permission\ListPermissions\ListPermissionsResult;
use Authorization\Presentation\Api\Provider\Permission\ListPermissionsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test ListPermissionsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPermissionsProvider::class)]
final class ListPermissionsProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsPermissions(): void
  {
    $permissionResult = new GetPermissionResult(
      id: '550e8400-e29b-41d4-a716-446655440030',
      name: 'users.create',
      description: 'Create users',
      createdAt: '2025-01-04 12:00:00',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new ListPermissionsResult(permissions: [$permissionResult]));

    $provider = new ListPermissionsProvider($queryBus);

    $result = $provider->provide(new GetCollection());

    self::assertCount(1, $result);
    self::assertSame('users.create', $result[0]->name);
  }
  // #endregion
}
