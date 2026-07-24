<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Application\UseCase\Command\Thread\StartAssistantThread\StartAssistantThreadResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test StartAssistantThreadResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(StartAssistantThreadResult::class)]
final class StartAssistantThreadResultTest extends TestCase
{
  #[Test]
  public function testCarriesTheThreadView(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $thread = new AssistantThreadView(
      id: 'thread-1',
      organizationId: 'org-1',
      memberId: 'member-1',
      title: null,
      model: null,
      createdAt: $now,
      updatedAt: $now,
      lastMessageAt: null,
    );

    $result = new StartAssistantThreadResult(thread: $thread);

    self::assertSame($thread, $result->thread);
  }
}
