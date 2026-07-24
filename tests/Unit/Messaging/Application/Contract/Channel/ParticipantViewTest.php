<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Channel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ParticipantView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ParticipantView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ParticipantView::class)]
final class ParticipantViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsAManualParticipant(): void
  {
    $addedAt = new DateTimeImmutable('2026-06-01T05:00:00+00:00');

    $view = new ParticipantView('conv-1', 'member-1', 'owner', 'manual', $addedAt);

    self::assertSame('conv-1', $view->conversationId);
    self::assertSame('member-1', $view->memberId);
    self::assertSame('owner', $view->role);
    self::assertSame('manual', $view->source);
    self::assertSame($addedAt, $view->addedAt);
  }

  #[Test]
  public function itRoundTripsATeamSourcedParticipantWithNoRole(): void
  {
    $view = new ParticipantView('conv-1', 'member-2', null, 'team', new DateTimeImmutable());

    self::assertNull($view->role);
    self::assertSame('team', $view->source);
  }
}
