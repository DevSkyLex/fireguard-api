<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Message\GetConversationActivity\{GetConversationActivityQuery, GetConversationActivityResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\ConversationActivityBucketOutput;
use Messaging\Presentation\Api\Provider\Conversation\GetConversationActivityProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};

/**
 * Test GetConversationActivityProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetConversationActivityProvider::class)]
final class GetConversationActivityProviderTest extends TestCase
{
  private const string CONVERSATION_ID = 'conversation-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProvideAsksTheQueryAndMapsEveryBucket(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetConversationActivityQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId
        && 26 === $query->buckets))
      ->willReturn(new GetConversationActivityResult([
        ['bucket' => '2026-07-20', 'count' => 0],
        ['bucket' => '2026-07-21', 'count' => 3],
      ]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request());

    $provider = new GetConversationActivityProvider($queryBus, $this->securityWithUser(), $requestStack);

    $result = $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);

    self::assertCount(2, $result);
    self::assertInstanceOf(ConversationActivityBucketOutput::class, $result[0]);
    self::assertSame('2026-07-20', $result[0]->bucket);
    self::assertSame(0, $result[0]->count);
    self::assertSame('2026-07-21', $result[1]->bucket);
    self::assertSame(3, $result[1]->count);
  }

  #[Test]
  public function testProvideForwardsTheBucketsQueryParameter(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetConversationActivityQuery $query): bool => 7 === $query->buckets))
      ->willReturn(new GetConversationActivityResult([]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request(query: ['buckets' => '7']));

    $provider = new GetConversationActivityProvider($queryBus, $this->securityWithUser(), $requestStack);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideClampsAnOversizedBucketsParameter(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetConversationActivityQuery $query): bool => 366 === $query->buckets))
      ->willReturn(new GetConversationActivityResult([]));

    $requestStack = new RequestStack();
    $requestStack->push(new Request(query: ['buckets' => '10000']));

    $provider = new GetConversationActivityProvider($queryBus, $this->securityWithUser(), $requestStack);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenConversationIdIsMissing(): void
  {
    $provider = new GetConversationActivityProvider(
      $this->createStub(QueryBusPort::class),
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

    $provider = new GetConversationActivityProvider($queryBus, $this->securityWithUser(), $requestStack);

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['conversationId' => self::CONVERSATION_ID]);
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
}
