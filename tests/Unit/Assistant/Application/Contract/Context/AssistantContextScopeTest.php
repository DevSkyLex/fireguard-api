<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\Contract\Context;

use Assistant\Application\Contract\Context\AssistantContextScope;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantContextScope.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantContextScope::class)]
final class AssistantContextScopeTest extends TestCase
{
  #[Test]
  public function testExposesActorAndThread(): void
  {
    $scope = new AssistantContextScope(actorUserId: 'user-1', threadId: 'thread-2');

    self::assertSame('user-1', $scope->actorUserId);
    self::assertSame('thread-2', $scope->threadId);
  }
}
