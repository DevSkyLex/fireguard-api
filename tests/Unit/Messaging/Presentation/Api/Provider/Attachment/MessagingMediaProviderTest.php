<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments\{
  ListConversationAttachmentsQuery,
  ListConversationAttachmentsResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingMessageRecord};
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Factory\MessageAttachmentOutputFactory;
use Messaging\Presentation\Api\Provider\Attachment\MessagingMediaProvider;
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
 * Test MessagingMediaProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMediaProvider::class)]
final class MessagingMediaProviderTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string ATTACHMENT_ID = '66666666-6666-4666-8666-666666666666';

  private const string MESSAGE_ID = '77777777-7777-4777-8777-777777777777';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsAPaginatorOverTheMappedAttachments(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListConversationAttachmentsQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId
        && 2 === $query->page
        && 15 === $query->itemsPerPage))
      ->willReturn(new ListConversationAttachmentsResult(
        new MessagingAttachmentPage([$this->attachment()], 2, 15, 16),
      ));

    $paginator = $this->createProvider($queryBus, ['page' => '2', 'itemsPerPage' => '15'])
      ->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);

    self::assertInstanceOf(TraversablePaginator::class, $paginator);

    /** @var list<MessageAttachmentOutput> $items */
    $items = iterator_to_array($paginator);

    self::assertCount(1, $items);
    self::assertSame(self::ATTACHMENT_ID, $items[0]->id);
    self::assertSame('file.pdf', $items[0]->fileName);
    self::assertSame(16.0, $paginator->getTotalItems());
  }

  #[Test]
  public function testProvideClampsPagingToTheAllowedBounds(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListConversationAttachmentsQuery $query): bool => 1 === $query->page
        && 100 === $query->itemsPerPage))
      ->willReturn(new ListConversationAttachmentsResult(new MessagingAttachmentPage([], 1, 100, 0)));

    $this->createProvider($queryBus, ['page' => '-3', 'itemsPerPage' => '900'])
      ->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenTheConversationIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider()->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new MessagingMediaProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      mapper: new MessageAttachmentOutputFactory(),
      security: $security,
      requestStack: $this->requestStack([]),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideMapsNotFoundExceptionToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessagingNotFoundException::conversation(self::CONVERSATION_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testOutputBuildsTheAttachmentDtoFromTheRecord(): void
  {
    $record = $this->record();

    $output = MessagingMediaProvider::output($record);

    self::assertSame(self::ATTACHMENT_ID, $output->id);
    self::assertSame('/api/messages/' . self::MESSAGE_ID, $output->message);
    self::assertSame('/api/conversations/' . self::CONVERSATION_ID, $output->conversation);
    self::assertSame('/api/organizations/org-1/members/member-1', $output->uploadedByMember);
    self::assertSame('/api/messaging-attachments/' . self::ATTACHMENT_ID . '/content', $output->contentUrl);
    self::assertSame('file.pdf', $output->fileName);
    self::assertSame('application/pdf', $output->mimeType);
    self::assertSame(100, $output->size);
    self::assertSame('Plan', $output->label);
    self::assertSame(3, $output->revision);
    self::assertSame('2026-01-01T08:30:00+00:00', $output->uploadedAt);
  }

  #[Test]
  public function testOutputThrowsWhenTheAttachmentHasNoMessage(): void
  {
    $record = $this->record();
    $record->message = null;

    $this->expectException(NotFoundHttpException::class);

    MessagingMediaProvider::output($record);
  }

  private function record(): MessagingAttachmentRecord
  {
    $message = new MessagingMessageRecord();
    $message->id = self::MESSAGE_ID;

    $record = new MessagingAttachmentRecord();
    $record->id = self::ATTACHMENT_ID;
    $record->message = $message;
    $record->conversationId = self::CONVERSATION_ID;
    $record->organizationId = 'org-1';
    $record->uploadedByMemberId = 'member-1';
    $record->fileName = 'file.pdf';
    $record->storagePath = 'org-1/conv-1/file.pdf';
    $record->mimeType = 'application/pdf';
    $record->size = 100;
    $record->label = 'Plan';
    $record->revision = 3;
    $record->uploadedAt = new DateTimeImmutable('2026-01-01T08:30:00+00:00');

    return $record;
  }

  /**
   * @param array<string, string> $queryParameters
   */
  private function createProvider(
    ?QueryBusPort $queryBus = null,
    array $queryParameters = [],
  ): MessagingMediaProvider {
    return new MessagingMediaProvider(
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      mapper: new MessageAttachmentOutputFactory(),
      security: $this->securityWithUser(),
      requestStack: $this->requestStack($queryParameters),
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

  private function attachment(): MessagingAttachment
  {
    return MessagingAttachment::create(
      id: MessagingAttachmentId::fromString(self::ATTACHMENT_ID),
      messageId: 'msg-1',
      conversationId: self::CONVERSATION_ID,
      organizationId: 'org-1',
      uploadedByMemberId: 'member-1',
      fileName: 'file.pdf',
      storagePath: 'org-1/conv-1/file.pdf',
      mimeType: 'application/pdf',
      size: 100,
    );
  }
  // #endregion
}
