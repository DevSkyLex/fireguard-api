<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Presentation\Api\Factory;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Presentation\Api\Factory\AssistantMessageOutputFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageOutputFactoryTest.
 *
 * @category Factory Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageOutputFactory::class)]
final class AssistantMessageOutputFactoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromViewMapsEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-02-06T10:00:00+00:00');
    $completedAt = new DateTimeImmutable('2026-02-06T10:00:04+00:00');

    $view = new AssistantMessageView(
      id: 'message-1',
      threadId: 'thread-1',
      organizationId: 'organization-1',
      role: 'assistant',
      body: 'The next inspection is due in March.',
      status: 'complete',
      errorCode: null,
      tokenCount: 64,
      createdAt: $createdAt,
      completedAt: $completedAt,
    );

    $output = new AssistantMessageOutputFactory()->fromView($view);

    self::assertSame('message-1', $output->id);
    self::assertSame('thread-1', $output->threadId);
    self::assertSame('organization-1', $output->organizationId);
    self::assertSame('assistant', $output->role);
    self::assertSame('The next inspection is due in March.', $output->body);
    self::assertSame('complete', $output->status);
    self::assertNull($output->errorCode);
    self::assertSame(64, $output->tokenCount);
    self::assertSame($createdAt->format('c'), $output->createdAt);
    self::assertSame($completedAt->format('c'), $output->completedAt);
  }

  #[Test]
  public function testFromViewLeavesCompletedAtNullWhenAbsent(): void
  {
    $view = new AssistantMessageView(
      id: 'message-2',
      threadId: 'thread-1',
      organizationId: 'organization-1',
      role: 'user',
      body: 'When is the next inspection?',
      status: 'pending',
      errorCode: 'model_unavailable',
      tokenCount: null,
      createdAt: new DateTimeImmutable('2026-02-06T09:59:00+00:00'),
      completedAt: null,
    );

    $output = new AssistantMessageOutputFactory()->fromView($view);

    self::assertNull($output->completedAt);
    self::assertNull($output->tokenCount);
    self::assertSame('model_unavailable', $output->errorCode);
    self::assertSame('pending', $output->status);
  }
  // #endregion
}
