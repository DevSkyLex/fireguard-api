<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Presentation\Api\Provider\Session;

use ApiPlatform\Metadata\Get;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Session\Application\UseCase\Query\Session\GetSession\{GetSessionQuery, GetSessionResult};
use Session\Domain\Exception\SessionNotFoundException;
use Session\Presentation\Api\Dto\Output\Session\SessionOutput;
use Session\Presentation\Api\Provider\Session\GetSessionProvider;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test GetSessionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetSessionProvider::class)]
final class GetSessionProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new GetSessionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideThrowsWhenIdMissing(): void
  {
    $user = $this->createStub(UserInterface::class);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $provider = new GetSessionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => null]);
  }

  #[Test]
  public function testProvideMapsResult(): void
  {
    $user = $this->createStub(UserInterface::class);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $result = new GetSessionResult(
      sessionId: 'session-1',
      userId: 'user-1',
      ipAddress: '127.0.0.1',
      userAgent: 'Mozilla',
      deviceType: 'desktop',
      browser: 'Chrome',
      operatingSystem: 'Windows',
      country: 'FR',
      city: 'Paris',
      rememberMe: false,
      createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
      lastActivityAt: new DateTimeImmutable('2024-01-02T00:00:00+00:00'),
      isRevoked: false,
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetSessionQuery::class))
      ->willReturn($result);

    $provider = new GetSessionProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['id' => 'session-1']);

    self::assertInstanceOf(SessionOutput::class, $output);
    self::assertSame('session-1', $output->id);
    self::assertTrue($output->isActive);
  }

  #[Test]
  public function testProvideMapsNotFoundException(): void
  {
    $user = $this->createStub(UserInterface::class);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(SessionNotFoundException::withId('session-2'));

    $provider = new GetSessionProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => 'session-2']);
  }

  #[Test]
  public function testProvideRethrowsUnrelatedException(): void
  {
    $user = $this->createStub(UserInterface::class);

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException('boom'));

    $provider = new GetSessionProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $provider->provide(new Get(), ['id' => 'session-3']);
  }
  // #endregion
}
