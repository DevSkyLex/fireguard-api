<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Adapter\Messenger;

use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use Assistant\Infrastructure\Adapter\Messenger\MessengerAssistantGenerationDispatcherAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};

/**
 * Test MessengerAssistantGenerationDispatcherAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessengerAssistantGenerationDispatcherAdapter::class)]
final class MessengerAssistantGenerationDispatcherAdapterTest extends TestCase
{
  #[Test]
  public function testEnqueueDispatchesAGenerateAssistantReplyCommandOnTheRawMessageBus(): void
  {
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (GenerateAssistantReplyCommand $command): bool {
        self::assertSame('org-1', $command->organizationId);
        self::assertSame('thread-1', $command->threadId);
        self::assertSame('user-msg-1', $command->userMessageId);
        self::assertSame('assistant-msg-1', $command->assistantMessageId);
        self::assertSame('llama3', $command->model);
        self::assertSame(0.5, $command->temperature);

        return true;
      }))
      ->willReturn(new Envelope(new GenerateAssistantReplyCommand('org-1', 'thread-1', 'user-msg-1', 'assistant-msg-1')));

    $adapter = new MessengerAssistantGenerationDispatcherAdapter($messageBus);

    $adapter->enqueue('org-1', 'thread-1', 'user-msg-1', 'assistant-msg-1', 'llama3', 0.5);
  }
}
