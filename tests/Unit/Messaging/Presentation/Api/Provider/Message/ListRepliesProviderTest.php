<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Message;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Query\Message\ListReplies\{ListRepliesQuery, ListRepliesResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Provider\Message\ListRepliesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};

use function iterator_to_array;

/**
 * Test ListRepliesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListRepliesProvider::class)]
final class ListRepliesProviderTest extends TestCase
{
  private const string PARENT_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string REPLY_MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441401';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProvideAsksTheQueryAndMapsThePage(): void
  {
    $view = $this->view();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListRepliesQuery $query): bool => self::USER_ID === $query->userId
        && self::PARENT_MESSAGE_ID === $query->parentMessageId
        && 1 === $query->page
        && 30 === $query->itemsPerPage))
      ->willReturn(new ListRepliesResult(new MessagePage([$view], 1, 30, 1), 'member-1'));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListRepliesProvider($queryBus, $this->outputFactory(), $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection(), ['id' => self::PARENT_MESSAGE_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(MessageOutput::class, $items[0]);
    self::assertSame(self::REPLY_MESSAGE_ID, $items[0]->id);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsMissing(): void
  {
    $provider = new ListRepliesProvider(
      $this->createStub(QueryBusPort::class),
      $this->outputFactory(),
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
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::message(self::PARENT_MESSAGE_ID));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new ListRepliesProvider($queryBus, $this->outputFactory(), $this->securityWithUser(), $requestStack);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['id' => self::PARENT_MESSAGE_ID]);
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([]);

    return new MessageOutputFactory($attachments, new MessageAttachmentOutputFactory(), $reactions, $savedMessages, $this->createStub(MessagingMemberDirectoryPort::class));
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
      self::REPLY_MESSAGE_ID,
      'conversation-1',
      'org-1',
      'author-1',
      'A reply',
      [],
      null,
      null,
      null,
      $now,
      $now,
      null,
      null,
      self::PARENT_MESSAGE_ID,
      0,
    );
  }
}
