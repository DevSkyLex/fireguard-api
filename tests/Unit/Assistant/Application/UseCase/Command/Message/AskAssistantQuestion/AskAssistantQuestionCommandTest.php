<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AskAssistantQuestionCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AskAssistantQuestionCommand::class)]
final class AskAssistantQuestionCommandTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $command = new AskAssistantQuestionCommand(
      organizationId: 'org-1',
      threadId: 'thread-2',
      actorUserId: 'user-3',
      body: 'How many extinguishers are overdue?',
      temperature: 0.4,
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('thread-2', $command->threadId);
    self::assertSame('user-3', $command->actorUserId);
    self::assertSame('How many extinguishers are overdue?', $command->body);
    self::assertSame(0.4, $command->temperature);
  }

  #[Test]
  public function testTemperatureDefaultsToNull(): void
  {
    $command = new AskAssistantQuestionCommand(
      organizationId: 'org-1',
      threadId: 'thread-2',
      actorUserId: 'user-3',
      body: 'A question.',
    );

    self::assertNull($command->temperature);
  }
}
