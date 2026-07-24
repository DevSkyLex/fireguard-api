<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Command\Conversation\FavoriteConversation\{FavoriteConversationCommand, FavoriteConversationResult};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Processor\Conversation\FavoriteConversationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};

/**
 * Test FavoriteConversationProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FavoriteConversationProcessor::class)]
final class FavoriteConversationProcessorTest extends TestCase
{
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessDispatchesTheFavoriteCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (FavoriteConversationCommand $command): bool => self::USER_ID === $command->userId
        && self::CONVERSATION_ID === $command->conversationId))
      ->willReturn(new FavoriteConversationResult($this->view()));

    $processor = new FavoriteConversationProcessor($commandBus, new ConversationOutputFactory(), $this->securityWithUser());

    $output = $processor->process(null, new Post(), ['id' => self::CONVERSATION_ID]);

    self::assertInstanceOf(ConversationOutput::class, $output);
    self::assertTrue($output->isFavorite);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new FavoriteConversationProcessor($this->createStub(CommandBusPort::class), new ConversationOutputFactory(), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), []);
  }

  #[Test]
  public function testProcessMapsNotFoundException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $processor = new FavoriteConversationProcessor($commandBus, new ConversationOutputFactory(), $this->securityWithUser());

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['id' => self::CONVERSATION_ID]);
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

    return new ConversationView(self::CONVERSATION_ID, 'org-1', 'facility', 'facility-1', 'subject', null, 1, false, $now, $now);
  }
}
