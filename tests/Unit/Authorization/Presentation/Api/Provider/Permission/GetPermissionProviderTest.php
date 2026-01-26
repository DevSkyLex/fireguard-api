<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Presentation\Api\Provider\Permission;

use ApiPlatform\Metadata\Get;
use Authorization\Application\UseCase\Query\Permission\GetPermission\{GetPermissionQuery, GetPermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Authorization\Presentation\Api\Provider\Permission\GetPermissionProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test GetPermissionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetPermissionProvider::class)]
final class GetPermissionProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideReturnsNullWhenIdMissing(): void
  {
    $provider = new GetPermissionProvider($this->createMock(QueryBusPort::class));

    $result = $provider->provide(new Get(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testProvideReturnsNullWhenMissing(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetPermissionQuery::class))
      ->willThrowException(PermissionNotFoundException::withId(permissionId: 'perm-1'));

    $provider = new GetPermissionProvider($queryBus);

    $result = $provider->provide(new Get(), ['id' => 'perm-1']);

    self::assertNull($result);
  }

  #[Test]
  public function testProvideMapsPermissionOutput(): void
  {
    $permissionResult = new GetPermissionResult(
      id: '550e8400-e29b-41d4-a716-446655440020',
      name: 'users.read',
      description: 'Read users',
      createdAt: '2025-01-03 12:00:00',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetPermissionQuery::class))
      ->willReturn($permissionResult);

    $provider = new GetPermissionProvider($queryBus);

    $output = $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655440020']);

    self::assertInstanceOf(PermissionOutput::class, $output);
    self::assertSame('users.read', $output->name);
  }
  // #endregion
}
