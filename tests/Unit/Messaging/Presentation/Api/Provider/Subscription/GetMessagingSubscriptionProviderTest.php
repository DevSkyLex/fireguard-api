<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Subscription;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Provider\Subscription\GetMessagingSubscriptionProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

/**
 * Test GetMessagingSubscriptionProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetMessagingSubscriptionProvider::class)]
final class GetMessagingSubscriptionProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441600';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441601';

  private const string CONVERSATION_ID = 'conversation-1';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetMessagingSubscriptionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
      defaultFactory: $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenTheIdIsMissing(): void
  {
    $provider = new GetMessagingSubscriptionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $this->securityWithUser(),
      defaultFactory: $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), []);
  }

  #[Test]
  public function testProvideBuildsATokenScopedToTheConversationTopic(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetConversationQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId))
      ->willReturn($this->conversationResult());

    $expectedTopic = '/organizations/' . self::ORG_ID . '/conversations/' . self::CONVERSATION_ID;

    /** @var TokenFactoryInterface&MockObject $tokenFactory */
    $tokenFactory = $this->createMock(TokenFactoryInterface::class);
    $tokenFactory->expects(self::once())
      ->method('create')
      ->with(
        [$expectedTopic],
        [],
        self::callback(static fn (array $claims): bool => $claims['exp'] instanceof DateTimeImmutable),
      )
      ->willReturn('jwt-token');

    $provider = new GetMessagingSubscriptionProvider(
      queryBus: $queryBus,
      security: $this->securityWithUser(),
      defaultFactory: $tokenFactory,
      tokenTtl: 60,
    );

    $output = $provider->provide(new Get(), ['id' => self::CONVERSATION_ID]);

    self::assertSame('jwt-token', $output->token);
    self::assertSame($expectedTopic, $output->topic);
  }

  #[Test]
  public function testProvideMapsADomainFailureToItsHttpCounterpart(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $provider = new GetMessagingSubscriptionProvider(
      queryBus: $queryBus,
      security: $this->securityWithUser(),
      defaultFactory: $this->createStub(TokenFactoryInterface::class),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::CONVERSATION_ID]);
  }

  private function conversationResult(): GetConversationResult
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    return new GetConversationResult(
      conversation: new ConversationView(self::CONVERSATION_ID, self::ORG_ID, 'facility', 'facility-1', 'subject', null, 0, false, $now, $now),
      subjectLabel: null,
      unreadCount: 0,
    );
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
