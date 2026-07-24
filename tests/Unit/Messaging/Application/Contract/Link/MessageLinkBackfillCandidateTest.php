<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Link;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\MessageLinkBackfillCandidate;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessageLinkBackfillCandidate.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageLinkBackfillCandidate::class)]
final class MessageLinkBackfillCandidateTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $extractedAt = new DateTimeImmutable('2026-04-01T07:00:00+00:00');

    $candidate = new MessageLinkBackfillCandidate('msg-1', 'conv-1', 'see https://example.com', $extractedAt, false);

    self::assertSame('msg-1', $candidate->messageId);
    self::assertSame('conv-1', $candidate->conversationId);
    self::assertSame('see https://example.com', $candidate->body);
    self::assertSame($extractedAt, $candidate->extractedAt);
    self::assertFalse($candidate->isDeleted);
  }

  #[Test]
  public function itFlagsATombstonedCandidate(): void
  {
    $candidate = new MessageLinkBackfillCandidate('msg-2', 'conv-1', '', new DateTimeImmutable(), true);

    self::assertTrue($candidate->isDeleted);
  }
}
