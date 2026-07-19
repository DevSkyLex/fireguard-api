<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Processor\Message;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\UseCase\Command\Message\RemoveReaction\{RemoveReactionCommand, RemoveReactionResult};
use Messaging\Domain\Exception\MessagingAccessDeniedException;
use Messaging\Presentation\Api\Processor\Message\RemoveReactionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Test RemoveReactionProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RemoveReactionProcessor::class)]
final class RemoveReactionProcessorTest extends TestCase
{
  private const string MESSAGE_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  #[Test]
  public function testProcessDispatchesTheRemoveReactionCommand(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (RemoveReactionCommand $command): bool => self::USER_ID === $command->userId
        && self::MESSAGE_ID === $command->messageId
        && "\u{1F44D}" === $command->emoji))
      ->willReturn(new RemoveReactionResult($this->view()));

    $processor = new RemoveReactionProcessor($commandBus, $this->securityWithUser());

    // `process()` is void on this 204 operation; the real assertion is the
    // command-bus expectation configured above.
    $processor->process(null, new Delete(), ['id' => self::MESSAGE_ID, 'emoji' => "\u{1F44D}"]);
  }

  #[Test]
  public function testProcessThrowsWhenIdIsMissing(): void
  {
    $processor = new RemoveReactionProcessor($this->createStub(CommandBusPort::class), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), ['emoji' => "\u{1F44D}"]);
  }

  #[Test]
  public function testProcessThrowsWhenEmojiIsMissing(): void
  {
    $processor = new RemoveReactionProcessor($this->createStub(CommandBusPort::class), $this->securityWithUser());

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::MESSAGE_ID]);
  }

  #[Test]
  public function testProcessMapsAccessDeniedException(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new MessagingAccessDeniedException('The current user is not an active member of the conversation organization.'));

    $processor = new RemoveReactionProcessor($commandBus, $this->securityWithUser());

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), ['id' => self::MESSAGE_ID, 'emoji' => "\u{1F44D}"]);
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
      'org-1',
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
