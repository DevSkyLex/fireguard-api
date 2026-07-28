<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Link;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Link\{MessagingLinkPage, MessagingLinkView};
use Messaging\Application\UseCase\Query\Link\ListConversationLinks\{ListConversationLinksQuery, ListConversationLinksResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\MessagingLinkOutput;
use Messaging\Presentation\Api\Factory\MessagingLinkOutputFactory;
use Messaging\Presentation\Api\Provider\Link\ListConversationLinksProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function iterator_to_array;

/**
 * Test ListConversationLinksProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationLinksProvider::class)]
final class ListConversationLinksProviderTest extends TestCase
{
  private const string CONVERSATION_ID = 'conversation-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string LINK_ID = '550e8400-e29b-41d4-a716-446655441500';

  #[Test]
  public function testProvideAsksTheQueryAndMapsThePage(): void
  {
    $view = $this->view();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListConversationLinksQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId
        && 1 === $query->page
        && 30 === $query->itemsPerPage))
      ->willReturn(new ListConversationLinksResult(new MessagingLinkPage([$view], 1, 30, 1)));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListConversationLinksProvider($queryBus, new MessagingLinkOutputFactory(), $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(MessagingLinkOutput::class, $items[0]);
    self::assertSame(self::LINK_ID, $items[0]->id);
    self::assertSame('https://example.com/report', $items[0]->url);
  }

  #[Test]
  public function testProvideThrowsWhenConversationIdIsMissing(): void
  {
    $provider = new ListConversationLinksProvider(
      $this->createStub(QueryBusPort::class),
      new MessagingLinkOutputFactory(),
      $this->securityWithUser(),
      new RequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideMapsNotFoundException(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListConversationLinksProvider($queryBus, new MessagingLinkOutputFactory(), $this->securityWithUser(), $requestStack);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListConversationLinksProvider($this->createStub(QueryBusPort::class), new MessagingLinkOutputFactory(), $security, new RequestStack());

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), []);
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

  private function view(): MessagingLinkView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessagingLinkView(self::LINK_ID, 'message-1', self::CONVERSATION_ID, 'https://example.com/report', null, $now);
  }
}
