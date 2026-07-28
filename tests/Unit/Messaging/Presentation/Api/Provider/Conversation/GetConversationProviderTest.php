<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Provider\Conversation\GetConversationProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test GetConversationProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetConversationProvider::class)]
final class GetConversationProviderTest extends TestCase
{
  private const string CONVERSATION_ID = 'conversation-1';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProvideAsksTheQueryAndMapsTheOutput(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetConversationQuery $query): bool => self::USER_ID === $query->userId
        && self::CONVERSATION_ID === $query->conversationId))
      ->willReturn(new GetConversationResult($this->view(), 'Site nord', 3, true));

    $provider = new GetConversationProvider($queryBus, new ConversationOutputFactory(), $this->securityWithUser());

    $output = $provider->provide(new Get(), ['id' => self::CONVERSATION_ID]);

    self::assertInstanceOf(ConversationOutput::class, $output);
    self::assertSame('Site nord', $output->subjectLabel);
    self::assertSame(3, $output->unreadCount);
    self::assertTrue($output->isFavorite);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsMissing(): void
  {
    $provider = new GetConversationProvider($this->createStub(QueryBusPort::class), new ConversationOutputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), []);
  }

  #[Test]
  public function testProvideMapsNotFoundException(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $provider = new GetConversationProvider($queryBus, new ConversationOutputFactory(), $this->securityWithUser());

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetConversationProvider($this->createStub(QueryBusPort::class), new ConversationOutputFactory(), $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), []);
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

    return new ConversationView(self::CONVERSATION_ID, 'org-1', 'facility', 'facility-1', 'subject', null, 5, false, $now, $now);
  }
}
