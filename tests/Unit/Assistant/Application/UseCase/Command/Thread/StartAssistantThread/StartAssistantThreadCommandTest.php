<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\StartAssistantThreadCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test StartAssistantThreadCommand.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartAssistantThreadCommand::class)]
final class StartAssistantThreadCommandTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $command = new StartAssistantThreadCommand(
      organizationId: 'org-1',
      actorUserId: 'user-2',
      title: 'Fire safety questions',
      model: 'llama3',
    );

    self::assertSame('org-1', $command->organizationId);
    self::assertSame('user-2', $command->actorUserId);
    self::assertSame('Fire safety questions', $command->title);
    self::assertSame('llama3', $command->model);
  }

  #[Test]
  public function testTitleAndModelDefaultToNull(): void
  {
    $command = new StartAssistantThreadCommand(
      organizationId: 'org-1',
      actorUserId: 'user-2',
    );

    self::assertNull($command->title);
    self::assertNull($command->model);
  }
}
