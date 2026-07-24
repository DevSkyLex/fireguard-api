<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Message\GenerateAssistantReply;

use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GenerateAssistantReplyCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateAssistantReplyCommand::class)]
final class GenerateAssistantReplyCommandTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $command = new GenerateAssistantReplyCommand(
      organizationId: 'org-1',
      threadId: 'thread-2',
      userMessageId: 'user-msg-3',
      assistantMessageId: 'assistant-msg-4',
      model: 'llama3',
      temperature: 0.7,
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('thread-2', $command->threadId);
    self::assertSame('user-msg-3', $command->userMessageId);
    self::assertSame('assistant-msg-4', $command->assistantMessageId);
    self::assertSame('llama3', $command->model);
    self::assertSame(0.7, $command->temperature);
  }

  #[Test]
  public function testModelAndTemperatureDefaultToNull(): void
  {
    $command = new GenerateAssistantReplyCommand(
      organizationId: 'org-1',
      threadId: 'thread-2',
      userMessageId: 'user-msg-3',
      assistantMessageId: 'assistant-msg-4',
    );

    self::assertNull($command->model);
    self::assertNull($command->temperature);
  }
}
