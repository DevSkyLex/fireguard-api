<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Message;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Port\Outbound\{
  MessagingAttachmentRepositoryPort,
  MessagingMemberDirectoryPort,
  MessagingReactionRepositoryPort,
  MessagingSavedMessageRepositoryPort
};
use Messaging\Application\UseCase\Query\Message\ListMessages\{ListMessagesQuery, ListMessagesResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Provider\Message\ListMessagesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

use function iterator_to_array;

/**
 * Test ListMessagesProvider.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListMessagesProvider::class)]
final class ListMessagesProviderTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441700';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441200';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsAPaginatorOverTheMappedMessages(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListMessagesQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId
        && 3 === $query->page
        && 10 === $query->itemsPerPage))
      ->willReturn(new ListMessagesResult(
        new MessagePage([$this->view()], 3, 10, 21),
        self::MEMBER_ID,
      ));

    $paginator = $this->createProvider($queryBus, ['page' => '3', 'itemsPerPage' => '10'])
      ->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $paginator);

    /** @var list<MessageOutput> $items */
    $items = iterator_to_array($paginator);

    self::assertCount(1, $items);
    self::assertSame(self::MESSAGE_ID, $items[0]->id);
    self::assertSame(21.0, $paginator->getTotalItems());
  }

  #[Test]
  public function testProvideClampsPagingToTheAllowedBounds(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListMessagesQuery $query): bool => 1 === $query->page
        && 100 === $query->itemsPerPage))
      ->willReturn(new ListMessagesResult(new MessagePage([], 1, 100, 0), self::MEMBER_ID));

    $this->createProvider($queryBus, ['page' => '0', 'itemsPerPage' => '999'])
      ->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenTheConversationIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class), [])
      ->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListMessagesProvider(
      $this->createStub(QueryBusPort::class),
      $this->outputFactory(),
      $security,
      $this->requestStack([]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideMapsNotFoundExceptionToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus, [])->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  /**
   * @param array<string, string> $queryParameters
   */
  private function createProvider(QueryBusPort $queryBus, array $queryParameters): ListMessagesProvider
  {
    return new ListMessagesProvider(
      $queryBus,
      $this->outputFactory(),
      $this->securityWithUser(),
      $this->requestStack($queryParameters),
    );
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    return new MessageOutputFactory(
      $attachments,
      new MessageAttachmentOutputFactory(),
      $reactions,
      $savedMessages,
      $this->createStub(MessagingMemberDirectoryPort::class),
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

  private function view(): MessageView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new MessageView(
      self::MESSAGE_ID,
      self::CONVERSATION_ID,
      'org-1',
      self::MEMBER_ID,
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
      null,
      null,
    );
  }
  // #endregion
}
