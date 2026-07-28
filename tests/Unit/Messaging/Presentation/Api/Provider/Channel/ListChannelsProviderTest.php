<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Channel;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelPage, ChannelView};
use Messaging\Application\UseCase\Query\Channel\ListChannels\{ListChannelsQuery, ListChannelsResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Dto\Output\ChannelOutput;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Provider\Channel\ListChannelsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function iterator_to_array;

/**
 * Test ListChannelsProvider.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelsProvider::class)]
final class ListChannelsProviderTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441100';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideMapsThePageAndDecoratesUnreadAndFavoriteFlags(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListChannelsQuery $query): bool => self::USER_ID === $query->userId
        && self::ORGANIZATION_ID === $query->organizationId
        && true === $query->isArchived
        && 2 === $query->page
        && 50 === $query->itemsPerPage))
      ->willReturn(new ListChannelsResult(
        new ChannelPage([$this->view()], 2, 50, 51),
        [self::CHANNEL_ID],
        [self::CHANNEL_ID => 7],
      ));

    $paginator = $this->createProvider($queryBus, [
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'isArchived' => '1',
      'page' => '2',
      'itemsPerPage' => '50',
    ])->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $paginator);

    /** @var list<ChannelOutput> $items */
    $items = iterator_to_array($paginator);

    self::assertCount(1, $items);
    self::assertSame(7, $items[0]->unreadCount);
    self::assertTrue($items[0]->isFavorite);
    self::assertSame(51.0, $paginator->getTotalItems());
  }

  #[Test]
  public function testProvideClampsPagingToTheAllowedBounds(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListChannelsQuery $query): bool => 1 === $query->page
        && 100 === $query->itemsPerPage
        && null === $query->isArchived))
      ->willReturn(new ListChannelsResult(new ChannelPage([], 1, 100, 0)));

    $this->createProvider($queryBus, [
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
      'page' => '-5',
      'itemsPerPage' => '5000',
    ])->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenTheOrganizationFilterIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class), [])->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListChannelsProvider(
      $this->createStub(QueryBusPort::class),
      new ChannelOutputFactory(),
      $security,
      $this->requestStack([]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsAccessDeniedExceptionToHttp403(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new MessagingAccessDeniedException('Not a member.'));

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProvider($queryBus, [
      'organization' => '/api/organizations/' . self::ORGANIZATION_ID,
    ])->provide(new GetCollection());
  }

  /**
   * @param array<string, string> $queryParameters
   */
  private function createProvider(QueryBusPort $queryBus, array $queryParameters): ListChannelsProvider
  {
    return new ListChannelsProvider(
      $queryBus,
      new ChannelOutputFactory(),
      $this->securityWithUser(),
      $this->requestStack($queryParameters),
    );
  }

  /**
   * @param array<string, string> $queryParameters
   */
  private function requestStack(array $queryParameters): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request($queryParameters));

    return $stack;
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
