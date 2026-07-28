<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\UseCase\Query\Conversation\ListDirectConversations\{ListDirectConversationsQuery, ListDirectConversationsResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Provider\Conversation\ListDirectConversationsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function iterator_to_array;

/**
 * Test ListDirectConversationsProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListDirectConversationsProvider::class)]
final class ListDirectConversationsProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string COUNTERPART_MEMBER_ID = '550e8400-e29b-41d4-a716-446655441600';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProvideAsksTheQueryAndMapsThePageIncludingTheCounterpartMember(): void
  {
    $view = $this->view();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListDirectConversationsQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && true === $query->isArchived
        && 1 === $query->page
        && 30 === $query->itemsPerPage))
      ->willReturn(new ListDirectConversationsResult(
        new ConversationPage([$view], 1, 30, 1),
        [self::CONVERSATION_ID => self::COUNTERPART_MEMBER_ID],
        [self::CONVERSATION_ID => 5],
        [self::CONVERSATION_ID],
      ));

    $request = new Request(['organization' => '/api/organizations/' . self::ORG_ID, 'isArchived' => 'true']);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListDirectConversationsProvider($queryBus, new ConversationOutputFactory(), $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(ConversationOutput::class, $items[0]);
    self::assertSame(self::CONVERSATION_ID, $items[0]->id);
    self::assertSame('/api/organizations/' . self::ORG_ID . '/members/' . self::COUNTERPART_MEMBER_ID, $items[0]->counterpartMember);
    self::assertSame(5, $items[0]->unreadCount);
    self::assertTrue($items[0]->isFavorite);
    self::assertNull($items[0]->name);
  }

  #[Test]
  public function testProvideThrowsWhenOrganizationIsMissing(): void
  {
    $provider = new ListDirectConversationsProvider(
      $this->createStub(QueryBusPort::class),
      new ConversationOutputFactory(),
      $this->securityWithUser(),
      new RequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideMapsMessagingExceptionsRaisedByTheQueryBus(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $requestStack = new RequestStack();
    $requestStack->push(new Request(['organization' => '/api/organizations/' . self::ORG_ID]));

    $provider = new ListDirectConversationsProvider($queryBus, new ConversationOutputFactory(), $this->securityWithUser(), $requestStack);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection());
  }

  #[Test]
  public function testProvideThrowsWhenTheUserIsNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListDirectConversationsProvider(
      $this->createStub(QueryBusPort::class),
      new ConversationOutputFactory(),
      $security,
      new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection());
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

  private function view(): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'direct', 'pair-key', 'participants', $now, 4, false, $now, $now);
  }
}
