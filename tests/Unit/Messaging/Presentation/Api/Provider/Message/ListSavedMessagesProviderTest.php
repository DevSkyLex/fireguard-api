<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Message;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use Messaging\Application\Port\Outbound\{MessagingAttachmentRepositoryPort, MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Query\Message\ListSavedMessages\{ListSavedMessagesQuery, ListSavedMessagesResult};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Provider\Message\ListSavedMessagesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function iterator_to_array;

/**
 * Test ListSavedMessagesProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListSavedMessagesProvider::class)]
final class ListSavedMessagesProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440500';

  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProvideAsksTheQueryAndMapsThePage(): void
  {
    $view = $this->view();

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListSavedMessagesQuery $query): bool => self::USER_ID === $query->userId
        && self::ORG_ID === $query->organizationId
        && 1 === $query->page
        && 30 === $query->itemsPerPage))
      ->willReturn(new ListSavedMessagesResult(new MessagePage([$view], 1, 30, 1), 'member-1'));

    $request = new Request(['organization' => '/api/organizations/' . self::ORG_ID]);
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $provider = new ListSavedMessagesProvider($queryBus, $this->outputFactory(), $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection());

    self::assertInstanceOf(TraversablePaginator::class, $result);
    self::assertSame(1.0, $result->getTotalItems());

    $items = iterator_to_array($result);
    self::assertCount(1, $items);
    self::assertInstanceOf(MessageOutput::class, $items[0]);
    self::assertSame(self::MESSAGE_ID, $items[0]->id);
  }

  #[Test]
  public function testProvideThrowsWhenOrganizationIsMissing(): void
  {
    $provider = new ListSavedMessagesProvider(
      $this->createStub(QueryBusPort::class),
      $this->outputFactory(),
      $this->securityWithUser(),
      new RequestStack(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection());
  }

  private function outputFactory(): MessageOutputFactory
  {
    $attachments = $this->createStub(MessagingAttachmentRepositoryPort::class);
    $attachments->method('findByMessageIds')->willReturn([]);

    $reactions = $this->createStub(MessagingReactionRepositoryPort::class);
    $reactions->method('findByMessageIds')->willReturn([]);

    $savedMessages = $this->createStub(MessagingSavedMessageRepositoryPort::class);
    $savedMessages->method('findSavedMessageIds')->willReturn([self::MESSAGE_ID]);

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
      self::MESSAGE_ID,
      'conversation-1',
      self::ORG_ID,
      'author-1',
      'Hello team',
      [],
      null,
      null,
      null,
      $now,
      $now,
    );
  }
}
