<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Channel;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use Messaging\Application\UseCase\Query\Channel\GetChannel\{GetChannelQuery, GetChannelResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Provider\Channel\GetChannelProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test GetChannelProvider.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetChannelProvider::class)]
final class GetChannelProviderTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsTheChannelWithItsUnreadAndFavoriteState(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetChannelQuery $query): bool => self::USER_ID === $query->userId
        && self::CHANNEL_ID === $query->conversationId))
      ->willReturn(new GetChannelResult($this->view(), 3, true));

    $output = $this->createProvider($queryBus)->provide(new Get(), ['id' => self::CHANNEL_ID]);

    self::assertSame(self::CHANNEL_ID, $output->id);
    self::assertSame(3, $output->unreadCount);
    self::assertTrue($output->isFavorite);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class))->provide(new Get(), []);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsEmpty(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class))->provide(new Get(), ['id' => '']);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetChannelProvider(
      $this->createStub(QueryBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProvideMapsNotFoundExceptionToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), ['id' => self::CHANNEL_ID]);
  }

  private function createProvider(QueryBusPort $queryBus): GetChannelProvider
  {
    return new GetChannelProvider($queryBus, new ChannelOutputFactory(), $this->securityWithUser());
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): ChannelView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ChannelView(
      self::CHANNEL_ID,
      self::ORGANIZATION_ID,
      'General',
      null,
      'creator-1',
      1,
      false,
      null,
      0,
      $now,
      $now,
      null,
    );
  }
  // #endregion
}
