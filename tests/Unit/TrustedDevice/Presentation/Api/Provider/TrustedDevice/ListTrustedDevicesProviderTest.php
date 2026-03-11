<?php

declare(strict_types=1);

namespace Tests\Unit\TrustedDevice\Presentation\Api\Provider\TrustedDevice;

use ApiPlatform\Metadata\GetCollection;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use TrustedDevice\Application\UseCase\Query\TrustedDevice\ListTrustedDevices\{ListTrustedDevicesQuery, TrustedDeviceItemResult};
use TrustedDevice\Presentation\Api\Provider\TrustedDevice\ListTrustedDevicesProvider;

use function iterator_to_array;

/**
 * Test ListTrustedDevicesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ListTrustedDevicesProvider::class)]
final class ListTrustedDevicesProviderTest extends TestCase
{
  // #region Methods
  /**
   * Method testProvideThrowsWhenNotAuthenticated.
   *
   * Test that provide throws when user is missing.
   */
  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new ListTrustedDevicesProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: [],
    );
  }

  /**
   * Method testProvideReturnsOutputsForUser.
   *
   * Test that provide maps results to outputs.
   */
  #[Test]
  public function testProvideReturnsOutputsForUser(): void
  {
    $item = new TrustedDeviceItemResult(
      id: 'device-123',
      name: 'Chrome on Windows',
      lastUsedAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      expiresAt: new DateTimeImmutable('2024-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2023-12-01T00:00:00+00:00'),
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(ListTrustedDevicesQuery::class))
      ->willReturn(new PaginatedResult(items: [$item], total: 1, limit: 1, offset: 0));

    $user = $this->createMock(UserInterface::class);
    $user->expects(self::once())
      ->method('getUserIdentifier')
      ->willReturn('user-123');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new ListTrustedDevicesProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $result = $provider->provide(
      operation: new GetCollection(),
      uriVariables: [],
      context: [],
    );

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertSame('device-123', $items[0]->id);
    self::assertSame('Chrome on Windows', $items[0]->name);
  }
  // #endregion
}
