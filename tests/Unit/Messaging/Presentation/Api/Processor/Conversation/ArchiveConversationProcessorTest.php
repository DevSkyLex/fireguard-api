<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Conversation;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\UseCase\Command\Conversation\ArchiveConversation\{
  ArchiveConversationCommand,
  ArchiveConversationResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Dto\Input\ArchiveConversationInput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Processor\Conversation\ArchiveConversationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test ArchiveConversationProcessor.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ArchiveConversationProcessor::class)]
final class ArchiveConversationProcessorTest extends TestCase
{
  // #region Constants
  private const string CONVERSATION_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProcessDispatchesTheArchiveCommandAndReturnsTheOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ArchiveConversationCommand $command): bool => self::USER_ID === $command->userId
        && self::CONVERSATION_ID === $command->conversationId
        && true === $command->isArchived))
      ->willReturn(new ArchiveConversationResult($this->view(true)));

    $input = new ArchiveConversationInput();
    $input->isArchived = true;

    $output = $this->createProcessor($commandBus)->process($input, new Patch(), ['id' => self::CONVERSATION_ID]);

    self::assertSame(self::CONVERSATION_ID, $output->id);
    self::assertTrue($output->isArchived);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(new ArchiveConversationInput(), new Patch(), []);
  }

  #[Test]
  public function testProcessThrowsWhenBodyIsInvalid(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($this->createStub(CommandBusPort::class))
      ->process(null, new Patch(), ['id' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ArchiveConversationProcessor(
      $this->createStub(CommandBusPort::class),
      new ConversationOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new ArchiveConversationInput(), new Patch(), ['id' => self::CONVERSATION_ID]);
  }

  #[Test]
  public function testProcessMapsNotFoundExceptionToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessagingNotFoundException::conversation(self::CONVERSATION_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)
      ->process(new ArchiveConversationInput(), new Patch(), ['id' => self::CONVERSATION_ID]);
  }

  private function createProcessor(CommandBusPort $commandBus): ArchiveConversationProcessor
  {
    return new ArchiveConversationProcessor($commandBus, new ConversationOutputFactory(), $this->securityWithUser());
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

  private function view(bool $isArchived): ConversationView
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return new ConversationView(
      self::CONVERSATION_ID,
      'org-1',
      'facility',
      'facility-1',
      'subject',
      null,
      1,
      $isArchived,
      $now,
      $now,
    );
  }
  // #endregion
}
