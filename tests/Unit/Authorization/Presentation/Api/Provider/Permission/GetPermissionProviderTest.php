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
    $provider = new GetPermissionProvider($this->createStub(QueryBusPort::class));

    $result = $provider->provide(new Get(), ['id' => null]);

    self::assertNull($result);
  }

  #[Test]
  public function testProvideLetsAMissingPermissionPropagate(): void
  {
    // It used to catch this and return null, so API Platform would answer 404.
    // The catch never fired: MessengerQueryBusAdapter wraps every handler
    // failure in MessengerRuntimeException, so production answered 500 while
    // this test passed — the mock threw the bare exception the adapter never
    // produces.
    //
    // The exception now propagates and `exception_to_status` maps it, which is
    // what makes the intended 404 actually happen. Proven by toggling the
    // configuration on the running endpoint: 500 without it, 404 with it.
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetPermissionQuery::class))
      ->willThrowException(PermissionNotFoundException::withId(permissionId: 'perm-1'));

    $this->expectException(PermissionNotFoundException::class);

    new GetPermissionProvider($queryBus)->provide(new Get(), ['id' => 'perm-1']);
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
